document.addEventListener('DOMContentLoaded', () => {
  const page = document.getElementById('settlementPage');

  if (!page) {
    return;
  }

  /*
   * PHP側のdata属性から会計情報を取得する。
   */
  const totalAmount = Number(page.dataset.totalAmount || 0);
  const splitMode = String(page.dataset.splitMode || 'NONE').toUpperCase();
  const remainingAmount = Number(
    page.dataset.remainingAmount || totalAmount
  );
  const currentPersonAmount = Number(
    page.dataset.currentPersonAmount || 0
  );

  /*
   * 金額を「¥1,000」の形式へ変換する。
   */
  const formatYen = (value) => {
    return '¥' + Number(value || 0).toLocaleString('ja-JP');
  };

  /*
   * 入力値から数字以外を取り除く。
   */
  const digitsOnly = (value) => {
    return String(value || '').replace(/\D/g, '');
  };

  /*
   * 入力欄の数値を取得する。
   */
  const toNumber = (value) => {
    return Number(digitsOnly(value) || 0);
  };

  /*
   * JavaScriptから入力欄へ金額を設定する。
   *
   * readonlyであってもJavaScriptからは値を変更できる。
   */
  const setInputValue = (input, value) => {
    if (!input || input.disabled) {
      return;
    }

    const normalizedValue = Math.max(0, Number(value || 0));

    input.value = normalizedValue > 0
      ? String(normalizedValue)
      : '';

    /*
     * inputイベントを発生させ、
     * おつり計算などを更新する。
     */
    input.dispatchEvent(
      new Event('input', {
        bubbles: true
      })
    );
  };

  /*
   * 端末標準キーボード・貼り付け・ドロップ入力を止める。
   *
   * readonlyだけでも通常の文字入力は防げるが、
   * 念のため追加で制御する。
   */
  const preventPhysicalInput = (input) => {
    if (!input) {
      return;
    }

    input.readOnly = true;
    input.setAttribute('inputmode', 'none');
    input.setAttribute('autocomplete', 'off');

    input.addEventListener('keydown', (event) => {
      /*
       * Tabによるフォーカス移動だけ許可する。
       */
      if (event.key !== 'Tab') {
        event.preventDefault();
      }
    });

    input.addEventListener('paste', (event) => {
      event.preventDefault();
    });

    input.addEventListener('drop', (event) => {
      event.preventDefault();
    });
  };

  /*
   * 共通の支払いブロック処理。
   *
   * 通常会計・分割会計・商品別会計で共通利用する。
   */
  const bindPaymentBlock = ({
    formId,
    receivedInputId,
    providerFieldId,
    providerInputId,
    keypadId,
    changeAmountId,
    quickSelector,
    payAmountSourceId = null,
    multiTargetKeypad = false,
    exactPayButtonId = null,
    exactReceivedButtonId = null,
    currentTargetLabelId = null
  }) => {
    const form = document.getElementById(formId);

    if (!form) {
      return null;
    }

    const radios = form.querySelectorAll(
      'input[name="pay_method"]'
    );

    const receivedInput = document.getElementById(
      receivedInputId
    );

    const payInput = payAmountSourceId
      ? document.getElementById(payAmountSourceId)
      : null;

    const providerField = document.getElementById(
      providerFieldId
    );

    const providerInput = providerInputId
      ? document.getElementById(providerInputId)
      : null;

    const keypad = keypadId
      ? document.getElementById(keypadId)
      : null;

    const changeAmount = document.getElementById(
      changeAmountId
    );

    const quickButtons = quickSelector
      ? form.querySelectorAll(quickSelector)
      : [];

    const exactPayButton = exactPayButtonId
      ? document.getElementById(exactPayButtonId)
      : null;

    const exactReceivedButton = exactReceivedButtonId
      ? document.getElementById(exactReceivedButtonId)
      : null;

    const currentTargetLabel = currentTargetLabelId
      ? document.getElementById(currentTargetLabelId)
      : null;

    /*
     * 現在テンキーから入力する対象。
     *
     * 通常会計・商品別会計:
     *   received
     *
     * 分割会計:
     *   初期状態はpay
     */
    let activeTarget = multiTargetKeypad
      ? 'pay'
      : 'received';

    let currentMethod = 'CASH';

    /*
     * 商品別分割で選択された商品の税抜・税額・税込合計を取得する。
     */
    const getItemSplitSelectedAmounts = () => {
      let subtotal = 0;
      let tax = 0;
      let count = 0;

      form
        .querySelectorAll('.item-split-checkbox:checked')
        .forEach((checkbox) => {
          const amount = Number(
            checkbox.dataset.amount || 0
          );

          const taxRate = Number(
            checkbox.dataset.taxRate || 0
          );

          subtotal += amount;
          tax += Math.ceil(amount * taxRate / 100);
          count++;
        });

      return {
        subtotal,
        tax,
        total: subtotal + tax,
        count
      };
    };

    /*
     * 今回の支払額を取得する。
     */
    const getPayAmount = () => {
      if (payInput) {
        return toNumber(payInput.value);
      }

      if (formId === 'itemSplitForm') {
        return getItemSplitSelectedAmounts().total;
      }

      return totalAmount;
    };

    /*
     * 現在選択中の支払方法を取得する。
     */
    const getSelectedMethod = () => {
      let selected = 'CASH';

      radios.forEach((radio) => {
        if (radio.checked) {
          selected = String(radio.value || 'CASH')
            .toUpperCase();
        }
      });

      return selected;
    };

    /*
     * 現在の入力対象の見た目を更新する。
     */
    const updateTargetView = () => {
      if (!multiTargetKeypad) {
        return;
      }

      if (payInput) {
        payInput.classList.toggle(
          'is-keypad-target',
          activeTarget === 'pay'
        );
      }

      if (receivedInput) {
        receivedInput.classList.toggle(
          'is-keypad-target',
          activeTarget === 'received'
        );
      }

      if (currentTargetLabel) {
        currentTargetLabel.textContent =
          activeTarget === 'pay'
            ? '支払額'
            : '受領金額';
      }
    };

    /*
     * テンキー入力先を変更する。
     */
    const setTarget = (target) => {
      if (!multiTargetKeypad) {
        return;
      }

      if (target === 'received') {
        /*
         * カード・電子マネーでは受領金額を入力対象にしない。
         */
        if (
          currentMethod !== 'CASH'
          || !receivedInput
          || receivedInput.disabled
        ) {
          activeTarget = 'pay';
        } else {
          activeTarget = 'received';
        }
      } else {
        activeTarget = 'pay';
      }

      updateTargetView();
    };

    /*
     * おつりを更新する。
     */
    const updateChange = () => {
      if (!changeAmount) {
        return;
      }

      /*
       * 現金以外ではおつり0円。
       */
      if (currentMethod !== 'CASH') {
        changeAmount.textContent = formatYen(0);
        return;
      }

      const received = receivedInput
        ? toNumber(receivedInput.value)
        : 0;

      const pay = getPayAmount();
      const change = received - pay;

      changeAmount.textContent = formatYen(
        change > 0 ? change : 0
      );
    };

    /*
     * 支払方法の選択状態を画面へ反映する。
     */
    const updatePayView = () => {
      currentMethod = getSelectedMethod();

      radios.forEach((radio) => {
        const label = radio.closest(
          '.payment-payitem'
        );

        if (label) {
          label.classList.toggle(
            'active',
            radio.checked
          );
        }
      });

      const isCash = currentMethod === 'CASH';
      const needsProvider = !isCash;

      /*
       * 現金以外では受領金額を使わない。
       */
      if (receivedInput) {
        receivedInput.disabled = !isCash;

        receivedInput.placeholder = isCash
          ? '受領金額を入力'
          : '現金支払い時のみ入力';

        if (!isCash) {
          /*
           * カード・電子マネーでは、
           * 支払額と同額を内部的に設定する。
           */
          receivedInput.value = String(
            getPayAmount()
          );

          setTarget('pay');
        }
      }

      /*
       * 「ちょうど受領」は現金時のみ利用可能。
       */
      if (exactReceivedButton) {
        exactReceivedButton.disabled = !isCash;
      }

      /*
       * 決済事業者欄を表示する。
       */
      if (providerField) {
        providerField.style.display =
          needsProvider ? 'block' : 'none';
      }

      if (providerInput && !needsProvider) {
        providerInput.value = '';
      }

      updateTargetView();
      updateChange();
    };

    /*
     * 支払方法変更。
     */
    radios.forEach((radio) => {
      radio.addEventListener(
        'change',
        updatePayView
      );
    });

    /*
     * 受領金額欄の処理。
     */
    if (receivedInput) {
      /*
       * 通常会計・商品別会計・分割会計で
       * 端末標準キーボードを禁止する。
       */
      preventPhysicalInput(receivedInput);

      receivedInput.addEventListener(
        'input',
        () => {
          receivedInput.value = digitsOnly(
            receivedInput.value
          );

          if (multiTargetKeypad) {
            setTarget('received');
          }

          updateChange();
        }
      );

      receivedInput.addEventListener(
        'focus',
        () => {
          if (currentMethod === 'CASH') {
            setTarget('received');
          }
        }
      );

      receivedInput.addEventListener(
        'click',
        () => {
          if (currentMethod === 'CASH') {
            setTarget('received');
          }
        }
      );
    }

    /*
     * 分割会計の支払額欄の処理。
     */
    if (payInput) {
      preventPhysicalInput(payInput);

      payInput.addEventListener(
        'input',
        () => {
          payInput.value = digitsOnly(
            payInput.value
          );

          setTarget('pay');

          /*
           * カード・電子マネーでは、
           * 受領額も支払額に合わせる。
           */
          if (
            currentMethod !== 'CASH'
            && receivedInput
          ) {
            receivedInput.value = payInput.value;
          }

          updateChange();
        }
      );

      payInput.addEventListener(
        'focus',
        () => {
          setTarget('pay');
        }
      );

      payInput.addEventListener(
        'click',
        () => {
          setTarget('pay');
        }
      );
    }

    /*
     * 既存の「ちょうど」ボタン。
     *
     * 通常会計と商品別会計で使用する。
     */
    quickButtons.forEach((button) => {
      button.addEventListener(
        'click',
        () => {
          const target =
            button.dataset.target || 'received';

          const amount =
            button.dataset.amount || '';

          if (target === 'pay' && payInput) {
            const value =
              amount === 'exact'
                ? getPayAmount()
                : Number(amount || 0);

            setInputValue(payInput, value);
            setTarget('pay');
            updateChange();
            return;
          }

          if (
            receivedInput
            && !receivedInput.disabled
          ) {
            const value =
              amount === 'exact'
                ? getPayAmount()
                : Number(amount || 0);

            setInputValue(
              receivedInput,
              value
            );

            if (multiTargetKeypad) {
              setTarget('received');
            }

            updateChange();
          }
        }
      );
    });

    /*
     * 「ちょうど支払」ボタン。
     */
    exactPayButton?.addEventListener(
      'click',
      () => {
        if (!payInput) {
          return;
        }

        /*
         * 人数分割の場合:
         * 現在の1人分の金額。
         *
         * 金額分割の場合:
         * 現在の残額。
         */
        let amount = remainingAmount;

        if (
          splitMode === 'PERSON'
          && currentPersonAmount > 0
        ) {
          amount = currentPersonAmount;
        }

        setInputValue(payInput, amount);

        /*
         * 現金の場合は次に受領金額へ入力することが多いため、
         * 入力先を受領金額へ移動する。
         */
        if (
          currentMethod === 'CASH'
          && receivedInput
          && !receivedInput.disabled
        ) {
          setTarget('received');
        } else {
          setTarget('pay');
        }

        /*
         * カード・電子マネーでは受領額も同額にする。
         */
        if (
          currentMethod !== 'CASH'
          && receivedInput
        ) {
          receivedInput.value = String(amount);
        }

        updateChange();
      }
    );

    /*
     * 「ちょうど受領」ボタン。
     */
    exactReceivedButton?.addEventListener(
      'click',
      () => {
        if (
          currentMethod !== 'CASH'
          || !receivedInput
          || receivedInput.disabled
        ) {
          return;
        }

        const pay = getPayAmount();

        if (pay <= 0) {
          window.alert(
            '先に支払額を入力してください。'
          );

          setTarget('pay');
          payInput?.focus();
          return;
        }

        setInputValue(receivedInput, pay);
        setTarget('received');
        updateChange();
      }
    );

    /*
     * オリジナルテンキー。
     */
    if (keypad) {
      keypad.addEventListener(
        'click',
        (event) => {
          const button = event.target.closest(
            '.key'
          );

          if (!button) {
            return;
          }

          let targetInput = receivedInput;

          /*
           * 分割会計では、
           * 現在選択中の支払額・受領金額へ入力する。
           */
          if (multiTargetKeypad) {
            if (activeTarget === 'pay') {
              targetInput = payInput;
            } else {
              targetInput = receivedInput;
            }
          }

          /*
           * readonlyは許可する。
           * disabledだけ入力不可にする。
           */
          if (
            !targetInput
            || targetInput.disabled
          ) {
            return;
          }

          const key = String(
            button.dataset.key || ''
          );

          let value = digitsOnly(
            targetInput.value
          );

          if (key === 'clear') {
            value = '';
          } else if (key === 'back') {
            value = value.slice(0, -1);
          } else if (key === '00') {
            /*
             * 空欄の状態で00を押しても、
             * 無意味な先頭00を作らない。
             */
            if (value !== '') {
              value += '00';
            }
          } else if (/^\d$/.test(key)) {
            /*
             * 先頭0を防止する。
             */
            if (value === '0') {
              value = key;
            } else {
              value += key;
            }
          }

          /*
           * 入力桁数を最大9桁に制限する。
           */
          value = value.slice(0, 9);

          targetInput.value = value;

          targetInput.dispatchEvent(
            new Event('input', {
              bubbles: true
            })
          );

          updateChange();
        }
      );
    }

    /*
     * フォーム送信前の入力チェック。
     */
    form.addEventListener(
      'submit',
      (event) => {
        const pay = getPayAmount();

        if (pay <= 0) {
          event.preventDefault();

          window.alert(
            formId === 'itemSplitForm'
              ? '会計する商品を選択してください。'
              : '支払額を入力してください。'
          );

          if (payInput) {
            setTarget('pay');
          }

          return;
        }

        if (currentMethod === 'CASH') {
          const received = receivedInput
            ? toNumber(receivedInput.value)
            : 0;

          if (received < pay) {
            event.preventDefault();

            window.alert(
              '受領金額が支払額より不足しています。'
            );

            if (multiTargetKeypad) {
              setTarget('received');
            }
          }
        }
      }
    );

    /*
     * 初期表示。
     */
    updatePayView();
    updateTargetView();
    updateChange();

    return {
      updateChange,
      getPayAmount,
      getItemSplitSelectedAmounts
    };
  };

  /*
   * 通常会計。
   */
  bindPaymentBlock({
    formId: 'paymentForm',
    receivedInputId: 'received_amount',
    providerFieldId: 'providerField',
    providerInputId: 'provider',
    keypadId: 'paymentKeypad',
    changeAmountId: 'changeAmount',
    quickSelector: '.quick-amount-btn'
  });

  /*
   * 人数分割・金額分割。
   */
  bindPaymentBlock({
    formId: 'splitPaymentForm',
    receivedInputId: 'split_received_amount',
    providerFieldId: 'splitProviderField',
    providerInputId: 'split_provider',
    keypadId: 'splitPaymentKeypad',
    changeAmountId: 'splitChangeAmount',
    quickSelector: '.split-pay-btn, .split-received-btn',
    payAmountSourceId: 'split_pay_amount',
    multiTargetKeypad: true,
    exactPayButtonId: 'splitExactPayBtn',
    exactReceivedButtonId: 'splitExactReceivedBtn',
    currentTargetLabelId: 'splitKeypadCurrentLabel'
  });

  /*
   * 商品別分割。
   */
  const itemBlock = bindPaymentBlock({
    formId: 'itemSplitForm',
    receivedInputId: 'item_received_amount',
    providerFieldId: 'itemProviderField',
    providerInputId: 'item_provider',
    keypadId: 'itemPaymentKeypad',
    changeAmountId: 'itemChangeAmount',
    quickSelector: '.item-received-btn'
  });

  /*
   * 商品別分割の商品選択金額を更新する。
   */
  const itemForm = document.getElementById(
    'itemSplitForm'
  );

  const itemCheckboxes = itemForm
    ? itemForm.querySelectorAll(
        '.item-split-checkbox:not(:disabled)'
      )
    : [];

  const itemSelectedTotal =
    document.getElementById(
      'itemSelectedTotal'
    );

  const itemSelectedSubtotal =
    document.getElementById(
      'itemSelectedSubtotal'
    );

  const itemSelectedTax =
    document.getElementById(
      'itemSelectedTax'
    );

  const itemSelectedCount =
    document.getElementById(
      'itemSelectedCount'
    );

  const updateItemSelection = () => {
    let subtotal = 0;
    let tax = 0;
    let count = 0;

    itemCheckboxes.forEach((checkbox) => {
      if (!checkbox.checked) {
        return;
      }

      const amount = Number(
        checkbox.dataset.amount || 0
      );

      const taxRate = Number(
        checkbox.dataset.taxRate || 0
      );

      subtotal += amount;
      tax += Math.ceil(
        amount * taxRate / 100
      );

      count++;
    });

    const total = subtotal + tax;

    if (itemSelectedSubtotal) {
      itemSelectedSubtotal.textContent =
        formatYen(subtotal);
    }

    if (itemSelectedTax) {
      itemSelectedTax.textContent =
        formatYen(tax);
    }

    if (itemSelectedTotal) {
      itemSelectedTotal.textContent =
        formatYen(total);
    }

    if (itemSelectedCount) {
      itemSelectedCount.textContent =
        `${count}件`;
    }

    if (
      itemBlock
      && typeof itemBlock.updateChange === 'function'
    ) {
      itemBlock.updateChange();
    }
  };

  itemCheckboxes.forEach((checkbox) => {
    checkbox.addEventListener(
      'change',
      updateItemSelection
    );
  });

  updateItemSelection();

  /*
   * 会計分割モーダル。
   */
  const splitModal =
    document.getElementById('splitModal');

  const openSplitModal =
    document.getElementById(
      'openSplitModal'
    );

  const closeSplitModal =
    document.getElementById(
      'closeSplitModal'
    );

  const splitTabs =
    document.querySelectorAll(
      '.split-tab'
    );

  const splitPanels =
    document.querySelectorAll(
      '.split-panel'
    );

  if (openSplitModal && splitModal) {
    openSplitModal.addEventListener(
      'click',
      () => {
        splitModal.hidden = false;
      }
    );
  }

  if (closeSplitModal && splitModal) {
    closeSplitModal.addEventListener(
      'click',
      () => {
        splitModal.hidden = true;
      }
    );
  }

  if (splitModal) {
    splitModal.addEventListener(
      'click',
      (event) => {
        if (event.target === splitModal) {
          splitModal.hidden = true;
        }
      }
    );
  }

  /*
   * Escapeキーでモーダルを閉じる。
   */
  document.addEventListener(
    'keydown',
    (event) => {
      if (
        event.key === 'Escape'
        && splitModal
        && !splitModal.hidden
      ) {
        splitModal.hidden = true;
      }
    }
  );

  /*
   * 分割方法タブ切替。
   */
  splitTabs.forEach((tab) => {
    tab.addEventListener(
      'click',
      () => {
        splitTabs.forEach((targetTab) => {
          targetTab.classList.remove(
            'active'
          );
        });

        splitPanels.forEach((panel) => {
          panel.classList.remove(
            'active'
          );
        });

        tab.classList.add('active');

        if (tab.dataset.tab === 'people') {
          document
            .getElementById(
              'splitPanelPeople'
            )
            ?.classList.add('active');
        } else if (
          tab.dataset.tab === 'amount'
        ) {
          document
            .getElementById(
              'splitPanelAmount'
            )
            ?.classList.add('active');
        } else if (
          tab.dataset.tab === 'item'
        ) {
          document
            .getElementById(
              'splitPanelItem'
            )
            ?.classList.add('active');
        }
      }
    );
  });

  /*
   * 人数分割の表示金額を更新する。
   */
  const splitPeople =
    document.getElementById(
      'split_people'
    );

  const splitPeopleResult =
    document.getElementById(
      'splitPeopleResult'
    );

  const updatePeopleSplit = () => {
    if (
      !splitPeople
      || !splitPeopleResult
    ) {
      return;
    }

    const people = Math.max(
      2,
      Number(splitPeople.value || 2)
    );

    const base = Math.floor(
      totalAmount / people
    );

    const remainder =
      totalAmount % people;

    const first =
      base + remainder;

    splitPeopleResult.textContent =
      `1人目: ${formatYen(first)}`
      + ` / 2人目以降: ${formatYen(base)}`;
  };

  if (splitPeople) {
    splitPeople.addEventListener(
      'input',
      updatePeopleSplit
    );

    updatePeopleSplit();
  }
});