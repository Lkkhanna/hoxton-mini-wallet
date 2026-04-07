<template>
  <div id="wallet-app">
    <header class="app-header">
      <div class="header-content">
        <div class="logo">
          <span class="logo-icon">🏦</span>
          <h1>Mini Wallet</h1>
        </div>
        <p class="tagline">Ledger-based financial system</p>
      </div>
    </header>

    <NotificationToast
      :show="notification.show"
      :message="notification.message"
      :type="notification.type"
      @close="closeNotification"
    />

    <main class="app-main">
      <div class="dashboard-grid">
        <div class="left-column">
          <AccountSelector
            :accounts="accounts"
            :selected-account-id="selectedAccountId"
            :loading="loadingAccounts"
            @account-selected="onAccountSelected"
            @create-account="showCreateModal = true"
          />

          <BalanceDisplay
            :account-id="selectedAccountId"
            :balance="balance"
            :loading="loadingBalance"
            :error="balanceError"
          />

          <TransferForm
            :accounts="accounts"
            :selected-account-id="selectedAccountId"
            :loading="transferring"
            :reset-key="transferResetKey"
            @transfer="onTransfer"
          />
        </div>

        <div class="right-column">
          <TransactionList
            :account-id="selectedAccountId"
            :transactions="transactions"
            :loading="loadingTransactions"
            :error="transactionsError"
          />
        </div>
      </div>
    </main>

    <CreateAccountModal
      :show="showCreateModal"
      :loading="creatingAccount"
      @close="showCreateModal = false"
      @create="onCreateAccount"
    />

    <footer class="app-footer">
      <p>Mini Wallet & Ledger System — Hoxton Wealth Assessment</p>
    </footer>
  </div>
</template>

<script>
import { v4 as uuidv4 } from 'uuid';
import api from '../services/api';
import AccountSelector from '../components/AccountSelector.vue';
import BalanceDisplay from '../components/BalanceDisplay.vue';
import TransferForm from '../components/TransferForm.vue';
import TransactionList from '../components/TransactionList.vue';
import CreateAccountModal from '../components/CreateAccountModal.vue';
import NotificationToast from '../components/NotificationToast.vue';

const PENDING_TRANSFER_STORAGE_KEY = 'wallet.pendingTransfer';

export default {
  name: 'DashboardView',

  components: {
    AccountSelector,
    BalanceDisplay,
    TransferForm,
    TransactionList,
    CreateAccountModal,
    NotificationToast,
  },

  data() {
    return {
      accounts: [],
      selectedAccountId: null,
      loadingAccounts: false,
      balance: null,
      loadingBalance: false,
      balanceError: null,
      transactions: [],
      loadingTransactions: false,
      transactionsError: null,
      transferring: false,
      showCreateModal: false,
      creatingAccount: false,
      transferResetKey: 0,
      pendingTransferAttempt: null,
      notification: {
        show: false,
        message: '',
        type: 'success',
      },
    };
  },

  watch: {
    selectedAccountId(newId) {
      if (newId) {
        this.fetchBalance();
        this.fetchTransactions();
      } else {
        this.balance = null;
        this.transactions = [];
      }
    },
  },

  created() {
    this.pendingTransferAttempt = this.loadPendingTransferAttempt();
    this.fetchAccounts();
  },

  methods: {
    async fetchAccounts() {
      this.loadingAccounts = true;
      try {
        const response = await api.listAccounts();
        this.accounts = response.data;

        if (
          this.selectedAccountId
          && !this.accounts.some(account => account.account_id === this.selectedAccountId)
        ) {
          this.selectedAccountId = null;
        }
      } catch (err) {
        this.showNotification(err.apiError?.message || 'Failed to load accounts', 'error');
      } finally {
        this.loadingAccounts = false;
      }
    },

    async fetchBalance() {
      if (!this.selectedAccountId) return;
      this.loadingBalance = true;
      this.balanceError = null;
      try {
        const response = await api.getBalance(this.selectedAccountId);
        this.balance = response.data.balance;
      } catch (err) {
        this.balanceError = err.apiError?.message || 'Failed to load balance';
        this.balance = null;
      } finally {
        this.loadingBalance = false;
      }
    },

    async fetchTransactions() {
      if (!this.selectedAccountId) return;
      this.loadingTransactions = true;
      this.transactionsError = null;
      try {
        const response = await api.getTransactions(this.selectedAccountId);
        this.transactions = response.data;
      } catch (err) {
        this.transactionsError = err.apiError?.message || 'Failed to load transactions';
        this.transactions = [];
      } finally {
        this.loadingTransactions = false;
      }
    },

    async refreshSelectedAccountData() {
      if (!this.selectedAccountId) return;

      await Promise.all([
        this.fetchBalance(),
        this.fetchTransactions(),
      ]);
    },

    onAccountSelected(accountId) {
      this.selectedAccountId = accountId;
    },

    async onCreateAccount({ accountId, name }) {
      this.creatingAccount = true;
      try {
        const response = await api.createAccount({ account_id: accountId, name });
        this.showCreateModal = false;
        this.showNotification(response.message, 'success');
        await this.fetchAccounts();
        this.selectedAccountId = accountId;
      } catch (err) {
        const msg = err.apiError?.errors?.account_id?.[0]
          || err.apiError?.message
          || 'Failed to create account';
        this.showNotification(msg, 'error');
      } finally {
        this.creatingAccount = false;
      }
    },

    async onTransfer({ fromAccountId, toAccountId, amount }) {
      this.transferring = true;
      const normalizedAmount = Number.parseFloat(amount).toFixed(2);
      const transferPayload = {
        from_account_id: fromAccountId,
        to_account_id: toAccountId,
        amount: normalizedAmount,
      };
      const fingerprint = this.createTransferFingerprint(transferPayload);
      const transactionId = this.resolveTransactionId(fingerprint, transferPayload);

      try {
        const response = await api.transfer({
          transaction_id: transactionId,
          from_account_id: fromAccountId,
          to_account_id: toAccountId,
          amount: Number.parseFloat(normalizedAmount),
        });

        this.completeTransferAttempt(fingerprint);
        this.showNotification(
          response.message,
          response.meta?.idempotency?.replayed ? 'info' : 'success'
        );

        await Promise.all([
          this.fetchAccounts(),
          this.refreshSelectedAccountData(),
        ]);
      } catch (err) {
        const preserveAttempt = this.shouldPreserveTransferAttempt(err);

        if (!preserveAttempt) {
          this.clearPendingTransferAttempt(fingerprint);
        }

        const msg = preserveAttempt
          ? 'Transfer result is unknown due to a network issue. Retrying the same transfer will reuse its transaction ID safely.'
          : (err.apiError?.message || 'Transfer failed');

        this.showNotification(msg, 'error');
      } finally {
        this.transferring = false;
      }
    },

    createTransferFingerprint({ from_account_id, to_account_id, amount }) {
      return `${from_account_id}:${to_account_id}:${amount}`;
    },

    resolveTransactionId(fingerprint, payload) {
      if (this.pendingTransferAttempt?.fingerprint === fingerprint) {
        return this.pendingTransferAttempt.transactionId;
      }

      const nextAttempt = {
        fingerprint,
        payload,
        transactionId: uuidv4(),
      };

      this.pendingTransferAttempt = nextAttempt;
      this.persistPendingTransferAttempt();

      return nextAttempt.transactionId;
    },

    shouldPreserveTransferAttempt(err) {
      const status = err.apiError?.status || 0;
      return status === 0 || status >= 500;
    },

    completeTransferAttempt(fingerprint) {
      this.clearPendingTransferAttempt(fingerprint);
      this.transferResetKey += 1;
    },

    clearPendingTransferAttempt(fingerprint = null) {
      if (fingerprint && this.pendingTransferAttempt?.fingerprint !== fingerprint) {
        return;
      }

      this.pendingTransferAttempt = null;
      window.localStorage.removeItem(PENDING_TRANSFER_STORAGE_KEY);
    },

    persistPendingTransferAttempt() {
      if (!this.pendingTransferAttempt) {
        window.localStorage.removeItem(PENDING_TRANSFER_STORAGE_KEY);
        return;
      }

      window.localStorage.setItem(
        PENDING_TRANSFER_STORAGE_KEY,
        JSON.stringify(this.pendingTransferAttempt)
      );
    },

    loadPendingTransferAttempt() {
      const rawValue = window.localStorage.getItem(PENDING_TRANSFER_STORAGE_KEY);
      if (!rawValue) {
        return null;
      }

      try {
        return JSON.parse(rawValue);
      } catch (error) {
        window.localStorage.removeItem(PENDING_TRANSFER_STORAGE_KEY);
        return null;
      }
    },

    showNotification(message, type = 'info') {
      this.notification = { show: true, message, type };
      setTimeout(() => {
        this.closeNotification();
      }, 5000);
    },

    closeNotification() {
      this.notification.show = false;
    },
  },
};
</script>
