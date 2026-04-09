<template>
  <div id="wallet-app">
    <header class="app-header">
      <div class="header-shell">
        <a
          class="brand-lockup"
          href="https://hoxtonwealth.com/"
          target="_blank"
          rel="noreferrer"
        >
          <img
            class="brand-logo"
            src="/hoxton-wealth-logo.svg"
            alt="Hoxton Wealth"
          >
        </a>

        <button
          class="mobile-nav-toggle"
          type="button"
          :aria-expanded="mobileNavOpen ? 'true' : 'false'"
          aria-controls="primary-navigation"
          aria-label="Toggle navigation"
          @click="toggleMobileNav"
        >
          <span class="mobile-nav-toggle-icon" aria-hidden="true"></span>
        </button>

        <nav
          id="primary-navigation"
          class="header-nav"
          :class="{ 'header-nav-open': mobileNavOpen }"
          aria-label="Primary"
        >
          <a href="#account-selector" @click="closeMobileNav">Accounts</a>
          <a href="#transfer-form" @click="closeMobileNav">Transfers</a>
          <a href="#balance-display" @click="closeMobileNav">Balance</a>
          <a href="#transaction-list" @click="closeMobileNav">History</a>
          <button
            class="header-nav-action"
            type="button"
            @click="openCreateAccountFromMenu"
          >
            New Account
          </button>
        </nav>

        <div class="header-meta">
          <button
            class="btn btn-secondary btn-sm desktop-header-action"
            @click="showCreateModal = true"
          >
            New Account
          </button>
        </div>
      </div>
    </header>

    <NotificationToast
      :show="notification.show"
      :message="notification.message"
      :type="notification.type"
      @close="closeNotification"
    />

    <main class="app-main">
      <section class="overview-band card">
        <div class="overview-copy">
          <p class="overview-kicker">Wallet overview</p>
          <h1>{{ selectedAccount ? selectedAccount.account_id : 'Select an account' }}</h1>
          <p>
            {{ selectedAccount
              ? `${selectedAccount.name || 'Selected account'} is ready for balance checks, transfers, and transfer history review.`
              : 'Choose an account to review balances, move funds, and inspect recent transaction activity.' }}
          </p>
        </div>

        <div class="overview-metrics">
          <div class="overview-stat">
            <span>Account Balance</span>
            <strong>{{ selectedAccountId && balance !== null ? formatCurrency(balance) : 'Awaiting selection' }}</strong>
          </div>
          <div class="overview-stat">
            <span>Total accounts</span>
            <strong>{{ accounts.length }}</strong>
          </div>
          <div class="overview-stat">
            <span>Portfolio balance</span>
            <strong>{{ totalPortfolioBalance }}</strong>
          </div>
        </div>
      </section>

      <div class="dashboard-grid">
        <div class="left-column">
          <AccountSelector
            :accounts="accounts"
            :selected-account-id="selectedAccountId"
            :selected-account="selectedAccount"
            :selected-balance="balance"
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
            :selected-account="selectedAccount"
            :available-balance="balance"
            :loading="transferring"
            :reset-key="transferResetKey"
            @transfer="onTransfer"
          />
        </div>

        <div class="right-column">
          <TransactionList
            :account-id="selectedAccountId"
            :transactions="transactions"
            :pagination="transactionsPagination"
            :loading="loadingTransactions"
            :error="transactionsError"
            @page-change="onTransactionsPageChange"
            @transaction-selected="openTransactionDetails"
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

    <TransactionDetailsModal
      :show="showTransactionModal"
      :transaction="selectedTransaction"
      :account-id="selectedAccountId"
      @close="closeTransactionDetails"
    />

    <footer class="app-footer">
      <div class="app-footer-inner">
        <p>Hoxton Mini Wallet</p>
        <p>Accounts, balances, transfers, and transaction history.</p>
      </div>
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
import TransactionDetailsModal from '../components/TransactionDetailsModal.vue';

// Persists the last ambiguous transfer attempt so a retry can reuse the same
// transaction_id after a network failure or timeout.
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
    TransactionDetailsModal,
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
      transactionsPagination: {
        currentPage: 1,
        lastPage: 1,
        perPage: 10,
        total: 0,
        from: null,
        to: null,
      },
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
      selectedTransaction: null,
      showTransactionModal: false,
      mobileNavOpen: false,
    };
  },

  computed: {
    selectedAccount() {
      return this.accounts.find(account => account.account_id === this.selectedAccountId) || null;
    },

    totalPortfolioBalance() {
      const total = this.accounts.reduce(
        (sum, account) => sum + Number.parseFloat(account.balance || 0),
        0
      );

      return this.formatCurrency(total);
    },
    isRefreshing() {
      return this.loadingAccounts || this.loadingBalance || this.loadingTransactions;
    },
  },

  watch: {
    selectedAccountId(newId) {
      this.closeTransactionDetails();

      if (newId) {
        this.transactionsPagination.currentPage = 1;
        this.fetchBalance();
        this.fetchTransactions();
      } else {
        this.balance = null;
        this.transactions = [];
        this.transactionsPagination = this.defaultTransactionsPagination();
      }
    },
  },

  created() {
    this.pendingTransferAttempt = this.loadPendingTransferAttempt();
    this.fetchAccounts();
  },

  methods: {
    // Reuse a consistent empty pagination shape whenever the selected account
    // changes or history loading fails.
    defaultTransactionsPagination() {
      return {
        currentPage: 1,
        lastPage: 1,
        perPage: 10,
        total: 0,
        from: null,
        to: null,
      };
    },

    formatCurrency(value) {
      return Number.parseFloat(value || 0).toLocaleString('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });
    },

    // Accounts drive the rest of the dashboard, so this method also clears an
    // invalid selection when the chosen account no longer exists in the latest list.
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

    // History is paginated server-side to keep the dashboard snappy as ledger
    // volume grows. The API meta block is normalized into a small local shape.
    async fetchTransactions() {
      if (!this.selectedAccountId) return;
      this.loadingTransactions = true;
      this.transactionsError = null;
      try {
        const response = await api.getTransactions(this.selectedAccountId, {
          page: this.transactionsPagination.currentPage,
          per_page: this.transactionsPagination.perPage,
        });
        this.transactions = response.data;
        const pagination = response.meta?.pagination || {};
        this.transactionsPagination = {
          currentPage: pagination.current_page || 1,
          lastPage: pagination.last_page || 1,
          perPage: pagination.per_page || this.transactionsPagination.perPage,
          total: pagination.total || 0,
          from: pagination.from ?? null,
          to: pagination.to ?? null,
        };
      } catch (err) {
        this.transactionsError = err.apiError?.message || 'Failed to load transactions';
        this.transactions = [];
        this.transactionsPagination = this.defaultTransactionsPagination();
      } finally {
        this.loadingTransactions = false;
      }
    },

    onTransactionsPageChange(page) {
      if (page === this.transactionsPagination.currentPage) return;
      this.transactionsPagination.currentPage = page;
      this.fetchTransactions();
    },

    toggleMobileNav() {
      this.mobileNavOpen = !this.mobileNavOpen;
    },

    closeMobileNav() {
      this.mobileNavOpen = false;
    },

    openCreateAccountFromMenu() {
      this.closeMobileNav();
      this.showCreateModal = true;
    },

    openTransactionDetails(transaction) {
      this.selectedTransaction = transaction;
      this.showTransactionModal = true;
    },

    closeTransactionDetails() {
      this.showTransactionModal = false;
      this.selectedTransaction = null;
    },

    async refreshSelectedAccountData() {
      if (!this.selectedAccountId) return;

      await Promise.all([
        this.fetchBalance(),
        this.fetchTransactions(),
      ]);
    },

    async refreshDashboard() {
      await this.fetchAccounts();
      await this.refreshSelectedAccountData();
    },

    onAccountSelected(accountId) {
      this.closeMobileNav();
      this.selectedAccountId = accountId;
    },

    // After creation we re-fetch the account list and select the canonical
    // account_id returned by the API rather than assuming the raw input value.
    async onCreateAccount({ accountId, name }) {
      this.creatingAccount = true;
      try {
        const response = await api.createAccount({ account_id: accountId, name });
        this.closeMobileNav();
        this.showCreateModal = false;
        this.showNotification(response.message, 'success');
        await this.fetchAccounts();
        this.selectedAccountId = response.data.account_id;
      } catch (err) {
        const msg = err.apiError?.errors?.account_id?.[0]
          || err.apiError?.message
          || 'Failed to create account';
        this.showNotification(msg, 'error');
      } finally {
        this.creatingAccount = false;
      }
    },

    // Transfers use a client-generated idempotency key. If the request result is
    // ambiguous, the same fingerprint can safely reuse that transaction_id on retry.
    async onTransfer({ fromAccountId, toAccountId, amount }) {
      this.transferring = true;
      const normalizedAmount = this.normalizeDecimalAmount(amount);
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
          amount: normalizedAmount,
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

    normalizeDecimalAmount(value) {
      const rawValue = String(value ?? "").trim();

      if (!rawValue) {
        return "0.00";
      }

      const negative = rawValue.startsWith("-");
      const unsignedValue = rawValue.replace(/^[-+]/, "");
      const [wholePart = "0", fractionPart = ""] = unsignedValue.split(".", 2);
      const normalizedWhole = wholePart.replace(/\D/g, "") || "0";
      const normalizedFraction = fractionPart.replace(/\D/g, "").slice(0, 2).padEnd(2, "0");

      return `${negative ? "-" : ""}${normalizedWhole}.${normalizedFraction}`;
    },

    createTransferFingerprint({ from_account_id, to_account_id, amount }) {
      return `${from_account_id}:${to_account_id}:${amount}`;
    },

    // Reuse the prior transaction_id only for the same pending fingerprint. This
    // favors safe retries over accidentally double-submitting an uncertain transfer.
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

    // The pending attempt lives in localStorage so a browser refresh does not
    // discard the idempotency key for an in-flight transfer.
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

<style scoped>
.overview-band {
  display: grid;
  grid-template-columns: minmax(0, 1.4fr) minmax(320px, 0.9fr);
  gap: 24px;
  margin-bottom: 28px;
  background: linear-gradient(135deg, rgba(255, 252, 246, 0.98), rgba(240, 250, 248, 0.92));
}

.overview-copy h1 {
  font-family: var(--font-family-display);
  font-size: 3rem;
  line-height: 0.92;
  font-weight: 600;
  margin-bottom: 12px;
}

.overview-copy p {
  max-width: 52ch;
  color: var(--color-ink-soft);
}

.overview-kicker {
  margin-bottom: 10px;
  font-size: 0.76rem;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--color-primary);
  font-weight: 800;
}

.overview-metrics {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 14px;
}

.overview-stat {
  padding: 18px;
  border-radius: 20px;
  background: rgba(255, 255, 255, 0.76);
  border: 1px solid rgba(13, 34, 56, 0.08);
}

.overview-stat span {
  display: block;
  margin-bottom: 8px;
  color: var(--color-ink-soft);
  font-size: 0.76rem;
  text-transform: uppercase;
  letter-spacing: 0.12em;
}

.overview-stat strong {
  display: block;
  font-size: 1.15rem;
  line-height: 1.2;
  color: var(--color-ink);
}

@media (max-width: 1080px) {
  .overview-band {
    grid-template-columns: 1fr;
  }

  .overview-metrics {
    grid-template-columns: 1fr;
  }
}
</style>
