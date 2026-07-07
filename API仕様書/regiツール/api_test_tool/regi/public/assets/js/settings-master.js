document.addEventListener('DOMContentLoaded', () => {
  const tabs = Array.from(document.querySelectorAll('.settings-tab'));
  const panels = Array.from(document.querySelectorAll('.settings-panel'));

  const activateTab = (name) => {
    const exists = tabs.some(tab => tab.dataset.tab === name);
    const targetName = exists ? name : 'accounts';

    tabs.forEach(tab => {
      tab.classList.toggle('is-active', tab.dataset.tab === targetName);
    });

    panels.forEach(panel => {
      panel.classList.toggle('is-active', panel.dataset.panel === targetName);
    });

    if (location.hash !== `#${targetName}`) {
      history.replaceState(null, '', `#${targetName}`);
    }
  };

  tabs.forEach(tab => {
    tab.addEventListener('click', () => activateTab(tab.dataset.tab));
  });

  const initialTab = location.hash.replace('#', '') || 'accounts';
  activateTab(initialTab);

  const lockBody = () => document.body.classList.add('is-modal-open');
  const unlockBody = () => {
    const anyOpen = Array.from(document.querySelectorAll('.modal-backdrop'))
      .some(modal => !modal.hidden);
    if (!anyOpen) {
      document.body.classList.remove('is-modal-open');
    }
  };

  const openModal = (modal) => {
    if (!modal) return;
    modal.hidden = false;
    lockBody();
  };

  const closeModal = (modal) => {
    if (!modal) return;
    modal.hidden = true;
    unlockBody();
  };

  // ------------------------------
  // 店舗追加モーダル
  // ------------------------------
  const storeModal = document.getElementById('storeModal');
  const btnOpenStoreModal = document.getElementById('btnOpenStoreModal');
  const btnCloseStoreModal = document.getElementById('btnCloseStoreModal');
  const btnCancelStoreModal = document.getElementById('btnCancelStoreModal');
  const addStoreNameInput = document.getElementById('store_name');

  const openStoreModal = () => {
    openModal(storeModal);
    setTimeout(() => {
      addStoreNameInput?.focus();
    }, 0);
  };

  const closeStoreModal = () => closeModal(storeModal);

  btnOpenStoreModal?.addEventListener('click', openStoreModal);
  btnCloseStoreModal?.addEventListener('click', closeStoreModal);
  btnCancelStoreModal?.addEventListener('click', closeStoreModal);

  storeModal?.addEventListener('click', (e) => {
    if (e.target === storeModal) {
      closeStoreModal();
    }
  });

  // ------------------------------
  // 店舗詳細モーダル
  // ------------------------------
  const storeDetailModal = document.getElementById('storeDetailModal');
  const btnCloseStoreDetailModal = document.getElementById('btnCloseStoreDetailModal');
  const btnCloseStoreDetailModalBottom = document.getElementById('btnCloseStoreDetailModalBottom');

  const detailStoreId = document.getElementById('detail_store_id');
  const detailStoreName = document.getElementById('detail_store_name');
  const detailStoreAddress = document.getElementById('detail_store_address');
  const detailStorePhone = document.getElementById('detail_store_phone');
  const detailStoreStatus = document.getElementById('detail_store_status');

  const openStoreDetailModal = (button) => {
    if (!button) return;

    const storeId = button.dataset.storeId ?? '';
    const storeName = button.dataset.storeName ?? '';
    const storeAddress = button.dataset.storeAddress ?? '';
    const storePhone = button.dataset.storePhone ?? '';
    const isActive = button.dataset.storeActive === '1';

    if (detailStoreId) detailStoreId.value = storeId;
    if (detailStoreName) detailStoreName.value = storeName;
    if (detailStoreAddress) detailStoreAddress.value = storeAddress;
    if (detailStorePhone) detailStorePhone.value = storePhone;
    if (detailStoreStatus) detailStoreStatus.value = isActive ? '有効' : '無効';

    openModal(storeDetailModal);
  };

  const closeStoreDetailModal = () => closeModal(storeDetailModal);

  document.querySelectorAll('.btn-store-detail').forEach(button => {
    button.addEventListener('click', () => openStoreDetailModal(button));
  });

  btnCloseStoreDetailModal?.addEventListener('click', closeStoreDetailModal);
  btnCloseStoreDetailModalBottom?.addEventListener('click', closeStoreDetailModal);

  storeDetailModal?.addEventListener('click', (e) => {
    if (e.target === storeDetailModal) {
      closeStoreDetailModal();
    }
  });

  // ------------------------------
  // 店舗編集モーダル
  // ------------------------------
  const storeEditModal = document.getElementById('storeEditModal');
  const btnCloseStoreEditModal = document.getElementById('btnCloseStoreEditModal');
  const btnCancelStoreEditModal = document.getElementById('btnCancelStoreEditModal');

  const editStoreIdView = document.getElementById('edit_store_id_view');
  const editStoreId = document.getElementById('edit_store_id');
  const editStoreName = document.getElementById('edit_store_name');
  const editStoreAddress = document.getElementById('edit_store_address');
  const editStorePhone = document.getElementById('edit_store_phone');
  const editIsActive = document.getElementById('edit_is_active');

  const openStoreEditModal = (button) => {
    if (!button) return;

    const storeId = button.dataset.storeId ?? '';
    const storeName = button.dataset.storeName ?? '';
    const storeAddress = button.dataset.storeAddress ?? '';
    const storePhone = button.dataset.storePhone ?? '';
    const isActive = button.dataset.storeActive ?? '1';

    if (editStoreIdView) editStoreIdView.value = storeId;
    if (editStoreId) editStoreId.value = storeId;
    if (editStoreName) editStoreName.value = storeName;
    if (editStoreAddress) editStoreAddress.value = storeAddress;
    if (editStorePhone) editStorePhone.value = storePhone;
    if (editIsActive) editIsActive.value = isActive;

    openModal(storeEditModal);

    setTimeout(() => {
      editStoreName?.focus();
    }, 0);
  };

  const closeStoreEditModal = () => closeModal(storeEditModal);

  document.querySelectorAll('.btn-store-edit').forEach(button => {
    button.addEventListener('click', () => openStoreEditModal(button));
  });

  btnCloseStoreEditModal?.addEventListener('click', closeStoreEditModal);
  btnCancelStoreEditModal?.addEventListener('click', closeStoreEditModal);

  storeEditModal?.addEventListener('click', (e) => {
    if (e.target === storeEditModal) {
      closeStoreEditModal();
    }
  });

  // ------------------------------
  // アカウント追加モーダル
  // ------------------------------
  const accountModal = document.getElementById('accountModal');
  const btnOpenAccountModal = document.getElementById('btnOpenAccountModal');
  const btnCloseAccountModal = document.getElementById('btnCloseAccountModal');
  const btnCancelAccountModal = document.getElementById('btnCancelAccountModal');
  const accountLoginIdInput = document.getElementById('account_login_id');

  const openAccountModal = () => {
    openModal(accountModal);
    setTimeout(() => {
      accountLoginIdInput?.focus();
    }, 0);
  };

  const closeAccountModal = () => closeModal(accountModal);

  btnOpenAccountModal?.addEventListener('click', openAccountModal);
  btnCloseAccountModal?.addEventListener('click', closeAccountModal);
  btnCancelAccountModal?.addEventListener('click', closeAccountModal);

  accountModal?.addEventListener('click', (e) => {
    if (e.target === accountModal) {
      closeAccountModal();
    }
  });

  // ------------------------------
  // アカウント詳細モーダル
  // ------------------------------
  const accountDetailModal = document.getElementById('accountDetailModal');
  const btnCloseAccountDetailModal = document.getElementById('btnCloseAccountDetailModal');
  const btnCloseAccountDetailModalBottom = document.getElementById('btnCloseAccountDetailModalBottom');

  const detailAccountId = document.getElementById('detail_account_id');
  const detailLoginId = document.getElementById('detail_login_id');
  const detailAccountName = document.getElementById('detail_account_name');
  const detailRoleType = document.getElementById('detail_role_type');
  const detailAccountStore = document.getElementById('detail_account_store');
  const detailAccountEmail = document.getElementById('detail_account_email');
  const detailAccountStatus = document.getElementById('detail_account_status');
  const detailAccountLastLogin = document.getElementById('detail_account_last_login');

  const openAccountDetailModal = (button) => {
    if (!button) return;

    const accountId = button.dataset.accountId ?? '';
    const loginId = button.dataset.loginId ?? '';
    const accountName = button.dataset.accountName ?? '';
    const roleType = button.dataset.roleType ?? '';
    const storeId = button.dataset.storeId ?? '';
    const storeName = button.dataset.storeName ?? '';
    const email = button.dataset.email ?? '';
    const isActive = button.dataset.isActive === '1';
    const lastLoginAt = button.dataset.lastLoginAt ?? '—';

    if (detailAccountId) detailAccountId.value = accountId;
    if (detailLoginId) detailLoginId.value = loginId;
    if (detailAccountName) detailAccountName.value = accountName;
    if (detailRoleType) detailRoleType.value = roleType === 'MASTER' ? 'マスター' : 'スタッフ';
    if (detailAccountStore) {
      detailAccountStore.value = roleType === 'MASTER'
        ? '全店舗'
        : (storeName ? `${storeId} / ${storeName}` : storeId);
    }
    if (detailAccountEmail) detailAccountEmail.value = email || '—';
    if (detailAccountStatus) detailAccountStatus.value = isActive ? '有効' : '無効';
    if (detailAccountLastLogin) detailAccountLastLogin.value = lastLoginAt || '—';

    openModal(accountDetailModal);
  };

  const closeAccountDetailModal = () => closeModal(accountDetailModal);

  document.querySelectorAll('.btn-account-detail').forEach(button => {
    button.addEventListener('click', () => openAccountDetailModal(button));
  });

  btnCloseAccountDetailModal?.addEventListener('click', closeAccountDetailModal);
  btnCloseAccountDetailModalBottom?.addEventListener('click', closeAccountDetailModal);

  accountDetailModal?.addEventListener('click', (e) => {
    if (e.target === accountDetailModal) {
      closeAccountDetailModal();
    }
  });

  // ------------------------------
  // アカウント編集モーダル
  // ------------------------------
  const accountEditModal = document.getElementById('accountEditModal');
  const btnCloseAccountEditModal = document.getElementById('btnCloseAccountEditModal');
  const btnCancelAccountEditModal = document.getElementById('btnCancelAccountEditModal');

  const editAccountId = document.getElementById('edit_account_id');
  const editAccountIdView = document.getElementById('edit_account_id_view');
  const editLoginId = document.getElementById('edit_login_id');
  const editAccountName = document.getElementById('edit_account_name');
  const editAccountPassword = document.getElementById('edit_account_password');
  const editRoleTypeView = document.getElementById('edit_role_type_view');
  const editAccountStoreId = document.getElementById('edit_account_store_id');
  const editAccountStoreHelp = document.getElementById('edit_account_store_help');
  const editAccountEmail = document.getElementById('edit_account_email');
  const editAccountIsActive = document.getElementById('edit_account_is_active');
  const editAccountIsActiveHidden = document.getElementById('edit_account_is_active_hidden');
  const editAccountStatusHelp = document.getElementById('edit_account_status_help');
  


  const openAccountEditModal = (button) => {
    if (!button) return;

    const accountId = button.dataset.accountId ?? '';
    const loginId = button.dataset.loginId ?? '';
    const accountName = button.dataset.accountName ?? '';
    const roleType = String(button.dataset.roleType ?? '').toUpperCase();
    const storeId = button.dataset.storeId ?? '';
    const email = button.dataset.email ?? '';
    const isActive = button.dataset.isActive ?? '1';

    const isMaster = roleType === 'MASTER';

    if (editAccountId) editAccountId.value = accountId;
    if (editAccountIdView) editAccountIdView.value = accountId;
    if (editLoginId) editLoginId.value = loginId;
    if (editAccountName) editAccountName.value = accountName;
    if (editAccountPassword) editAccountPassword.value = '';
    if (editRoleTypeView) editRoleTypeView.value = isMaster ? 'マスター' : 'スタッフ';
    if (editAccountEmail) editAccountEmail.value = email;
    if (editAccountIsActive) {
      editAccountIsActive.value = isActive;

      if (isMaster) {
        editAccountIsActive.disabled = true;
      } else {
        editAccountIsActive.disabled = false;
      }
    }

    if (editAccountIsActiveHidden) {
      editAccountIsActiveHidden.value = isActive;
    }

    if (editAccountStatusHelp) {
      editAccountStatusHelp.textContent = isMaster
        ? 'マスター管理者の有効・無効はこの画面では変更できません。'
        : 'アカウントの有効・無効を設定できます。';
    }

    if (editAccountStoreId) {
      if (isMaster) {
        editAccountStoreId.value = '';
        editAccountStoreId.disabled = true;
        editAccountStoreId.required = false;
      } else {
        editAccountStoreId.disabled = false;
        editAccountStoreId.required = true;
        editAccountStoreId.value = storeId;
      }
    }

    if (editAccountStoreHelp) {
      editAccountStoreHelp.textContent = isMaster
        ? 'マスター管理者は全店舗を管理するため、所属店舗の選択は不要です。'
        : 'スタッフの場合は所属店舗を選択してください。';
    }

    openModal(accountEditModal);

    setTimeout(() => {
      editLoginId?.focus();
    }, 0);
  };

  editAccountIsActive?.addEventListener('change', () => {
    if (editAccountIsActiveHidden) {
      editAccountIsActiveHidden.value = editAccountIsActive.value;
    }
  });

  const closeAccountEditModal = () => closeModal(accountEditModal);

  document.querySelectorAll('.btn-account-edit').forEach(button => {
    button.addEventListener('click', () => openAccountEditModal(button));
  });

  btnCloseAccountEditModal?.addEventListener('click', closeAccountEditModal);
  btnCancelAccountEditModal?.addEventListener('click', closeAccountEditModal);

  accountEditModal?.addEventListener('click', (e) => {
    if (e.target === accountEditModal) {
      closeAccountEditModal();
    }
  });

  // ------------------------------
  // Escapeで全部閉じる
  // ------------------------------
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      closeStoreModal();
      closeStoreDetailModal();
      closeStoreEditModal();
      closeAccountModal();
      closeAccountDetailModal();
      closeAccountEditModal();
    }
  });
});