document.addEventListener("DOMContentLoaded", () => {
  "use strict";

  const BASE_URL = "/regi/public";

  const modal = document.getElementById("historyModal");
  const modalBody = document.getElementById("historyModalBody");
  const closeBtn = document.getElementById("historyClose");

  const historyCards = document.querySelectorAll(".history-card");

  let lastFocusedElement = null;
  let currentRequestController = null;

  /**
   * 会計詳細モーダルを表示する
   */
  const showModal = () => {
    if (!modal) {
      return;
    }

    modal.classList.add("show");
    modal.classList.add("is-open");
    modal.setAttribute("aria-hidden", "false");

    document.body.classList.add("history-modal-open");

    if (closeBtn) {
      closeBtn.focus();
    }
  };

  /**
   * 会計詳細モーダルを閉じる
   */
  const closeModal = () => {
    if (!modal) {
      return;
    }

    if (currentRequestController) {
      currentRequestController.abort();
      currentRequestController = null;
    }

    modal.classList.remove("show");
    modal.classList.remove("is-open");
    modal.setAttribute("aria-hidden", "true");

    document.body.classList.remove("history-modal-open");

    if (modalBody) {
      modalBody.innerHTML = `
        <div class="history-modal-loading">
          会計詳細を読み込んでいます。
        </div>
      `;
    }

    if (lastFocusedElement instanceof HTMLElement) {
      lastFocusedElement.focus();
    }

    lastFocusedElement = null;
  };

  /**
   * 読み込み中表示
   */
  const showLoading = () => {
    if (!modalBody) {
      return;
    }

    modalBody.innerHTML = `
      <div class="history-modal-loading">
        会計詳細を読み込んでいます。
      </div>
    `;
  };

  /**
   * エラー表示
   *
   * @param {string} message
   */
  const showError = (message) => {
    if (!modalBody) {
      return;
    }

    modalBody.innerHTML = "";

    const errorBox = document.createElement("div");
    errorBox.className = "history-modal-error";

    const messageElement = document.createElement("p");
    messageElement.textContent =
      message || "会計詳細の取得中にエラーが発生しました。";

    const guideElement = document.createElement("p");
    guideElement.textContent =
      "モーダルを閉じて、もう一度会計履歴を選択してください。";

    errorBox.appendChild(messageElement);
    errorBox.appendChild(guideElement);

    modalBody.appendChild(errorBox);
  };

  /**
   * 選択されている支払IDを取得する
   *
   * @returns {string}
   */
  const getSelectedPaymentId = () => {
    if (!modalBody) {
      return "";
    }

    const selected = modalBody.querySelector(
      'input[name="history_bill_payment_id"]:checked'
    );

    if (!(selected instanceof HTMLInputElement)) {
      return "";
    }

    return String(selected.value || "").trim();
  };

  /**
   * 選択行の見た目を更新する
   */
  const updateSelectedPaymentRow = () => {
    if (!modalBody) {
      return;
    }

    const rows = modalBody.querySelectorAll(".history-payment-row");

    rows.forEach((row) => {
      const radio = row.querySelector(
        'input[name="history_bill_payment_id"]'
      );

      const checked =
        radio instanceof HTMLInputElement && radio.checked;

      row.classList.toggle("is-selected", checked);
    });
  };

  /**
   * 支払い選択イベントを設定する
   */
  const initializePaymentSelection = () => {
    if (!modalBody) {
      return;
    }

    const radios = modalBody.querySelectorAll(
      'input[name="history_bill_payment_id"]'
    );

    radios.forEach((radio) => {
      radio.addEventListener("change", () => {
        updateSelectedPaymentRow();
      });
    });

    updateSelectedPaymentRow();
  };

  /**
   * 帳票ページへ移動する
   *
   * @param {"receipt"|"invoice"} type
   */
  const openPrintPage = (type) => {
    const paymentId = getSelectedPaymentId();

    if (!paymentId) {
      alert("再印刷する支払いを選択してください。");
      return;
    }

    const url =
      `${BASE_URL}/history/${type}` +
      `?bill_payment_id=${encodeURIComponent(paymentId)}`;

    window.location.href = url;
  };

  /**
   * 再印刷ボタンのイベントを設定する
   */
  const initializeReprintButtons = () => {
    if (!modalBody) {
      return;
    }

    const receiptButton = modalBody.querySelector(
      "#historyReceiptReprintButton"
    );

    const invoiceButton = modalBody.querySelector(
      "#historyInvoiceReprintButton"
    );

    if (receiptButton) {
      receiptButton.addEventListener("click", () => {
        openPrintPage("receipt");
      });
    }

    if (invoiceButton) {
      invoiceButton.addEventListener("click", () => {
        openPrintPage("invoice");
      });
    }
  };

  /**
   * 過去データ用のbill_id再印刷ボタンを設定する
   *
   * BILL_PAYMENTが存在しない過去データに対応する。
   */
  const initializeLegacyReprintButtons = () => {
    if (!modalBody) {
      return;
    }

    const receiptButton = modalBody.querySelector(
      "[data-legacy-receipt-bill-id]"
    );

    const invoiceButton = modalBody.querySelector(
      "[data-legacy-invoice-bill-id]"
    );

    if (receiptButton instanceof HTMLElement) {
      receiptButton.addEventListener("click", () => {
        const billId = String(
          receiptButton.dataset.legacyReceiptBillId || ""
        ).trim();

        if (!billId) {
          alert("会計IDが取得できませんでした。");
          return;
        }

        window.location.href =
          `${BASE_URL}/history/receipt` +
          `?bill_id=${encodeURIComponent(billId)}`;
      });
    }

    if (invoiceButton instanceof HTMLElement) {
      invoiceButton.addEventListener("click", () => {
        const billId = String(
          invoiceButton.dataset.legacyInvoiceBillId || ""
        ).trim();

        if (!billId) {
          alert("会計IDが取得できませんでした。");
          return;
        }

        window.location.href =
          `${BASE_URL}/history/invoice` +
          `?bill_id=${encodeURIComponent(billId)}`;
      });
    }
  };

  /**
   * モーダルへ読み込んだ要素のイベントを初期化する
   */
  const initializeModalContent = () => {
    initializePaymentSelection();
    initializeReprintButtons();
    initializeLegacyReprintButtons();
  };

  /**
   * 会計詳細を取得する
   *
   * @param {string} detailUrl
   */
  const loadBillDetail = async (detailUrl) => {
    if (!modalBody) {
      return;
    }

    if (currentRequestController) {
      currentRequestController.abort();
    }

    currentRequestController = new AbortController();

    showLoading();

    try {
      const response = await fetch(detailUrl, {
        method: "GET",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
        credentials: "same-origin",
        signal: currentRequestController.signal,
      });

      const responseText = await response.text();

      if (!response.ok) {
        throw new Error(
          responseText ||
          `会計詳細の取得に失敗しました。HTTP ${response.status}`
        );
      }

      modalBody.innerHTML = responseText;

      initializeModalContent();
    } catch (error) {
      if (
        error instanceof DOMException &&
        error.name === "AbortError"
      ) {
        return;
      }

      console.error("会計詳細取得エラー:", error);

      showError(
        error instanceof Error
          ? error.message
          : "会計詳細の取得中にエラーが発生しました。"
      );
    } finally {
      currentRequestController = null;
    }
  };

  /**
   * 会計履歴カードのクリックイベント
   */
  historyCards.forEach((card) => {
    card.addEventListener("click", async () => {
      let detailUrl = "";

      if (card instanceof HTMLElement) {
        detailUrl = String(
          card.dataset.historyDetailUrl || ""
        ).trim();

        /*
         * 古いindex.phpとの一時的な互換性。
         * data-history-detail-urlがない場合は、
         * data-json内のbillIdからURLを作成する。
         */
        if (!detailUrl) {
          try {
            const bill = JSON.parse(card.dataset.json || "{}");
            const billId = String(bill?.billId || "").trim();

            if (billId) {
              detailUrl =
                `${BASE_URL}/history/detail` +
                `?bill_id=${encodeURIComponent(billId)}`;
            }
          } catch (error) {
            console.error("会計情報の解析に失敗しました:", error);
          }
        }
      }

      if (!detailUrl) {
        alert("会計IDが取得できませんでした。");
        return;
      }

      lastFocusedElement =
        card instanceof HTMLElement ? card : null;

      showModal();
      await loadBillDetail(detailUrl);
    });
  });

  /**
   * 閉じるボタン
   */
  closeBtn?.addEventListener("click", closeModal);

  /**
   * 背景クリックで閉じる
   */
  modal?.addEventListener("click", (event) => {
    if (event.target === modal) {
      closeModal();
    }
  });

  /**
   * Escapeキーで閉じる
   */
  document.addEventListener("keydown", (event) => {
    if (
      event.key === "Escape" &&
      modal &&
      (
        modal.classList.contains("show") ||
        modal.classList.contains("is-open")
      )
    ) {
      closeModal();
    }
  });
});