<template>
  <div class="card" id="transfer-form">
    <div class="card-title">
      Transfers
    </div>

    <p class="transfer-intro">
      Move funds between accounts with protected retries and clear transfer review details.
    </p>

    <div v-if="selectedAccount" class="transfer-context">
      <div>
        <span class="transfer-context-label">Funding from</span>
        <strong>{{ selectedAccount.account_id }}</strong>
        <p>{{ selectedAccount.name || 'Primary operating account' }}</p>
      </div>
      <div class="transfer-context-balance">
        <span>Available</span>
        <strong>{{ formattedAvailableBalance }}</strong>
      </div>
    </div>

    <form @submit.prevent="handleSubmit">
      <div class="form-group">
        <label for="transfer-from">From Account</label>
        <select
          id="transfer-from"
          class="form-select"
          v-model="form.fromAccountId"
          required
        >
          <option value="" disabled>Select source account...</option>
          <option
            v-for="account in accounts"
            :key="'from-' + account.account_id"
            :value="account.account_id"
          >
            {{ account.account_id }}{{ account.name ? ` — ${account.name}` : '' }}
          </option>
        </select>
      </div>

      <div class="form-group">
        <label for="transfer-to">To Account</label>
        <select
          id="transfer-to"
          class="form-select"
          v-model="form.toAccountId"
          required
        >
          <option value="" disabled>Select destination account...</option>
          <option
            v-for="account in availableDestinations"
            :key="'to-' + account.account_id"
            :value="account.account_id"
          >
            {{ account.account_id }}{{ account.name ? ` — ${account.name}` : '' }}
          </option>
        </select>
      </div>

      <div class="form-group">
        <label for="transfer-amount">Amount ($)</label>
        <div class="amount-field">
          <span>$</span>
          <input
            id="transfer-amount"
            type="text"
            inputmode="decimal"
            autocomplete="off"
            class="form-input amount-input"
            :value="form.amount"
            placeholder="0.00"
            required
            @input="onAmountInput"
          />
        </div>
      </div>

      <div v-if="validationError" class="error-message" style="margin-bottom: 16px;">
        {{ validationError }}
      </div>

      <div class="transfer-summary">
        <span class="transfer-summary-label">Transfer review</span>
        <strong>{{ summaryHeadline }}</strong>
        <span>{{ summaryLabel }}</span>
      </div>

      <button
        id="btn-transfer"
        type="submit"
        class="btn btn-primary btn-full"
        :disabled="!isValid || loading"
      >
        <span v-if="loading" class="spinner" style="border-top-color: white;"></span>
        {{ loading ? 'Processing transfer...' : 'Submit transfer' }}
      </button>
    </form>
  </div>
</template>

<script>
export default {
  name: 'TransferForm',
  props: {
    accounts: { type: Array, default: () => [] },
    selectedAccountId: { type: String, default: null },
    selectedAccount: { type: Object, default: null },
    availableBalance: { type: String, default: null },
    loading: { type: Boolean, default: false },
    resetKey: { type: Number, default: 0 },
  },

  data() {
    return {
      form: {
        fromAccountId: '',
        toAccountId: '',
        amount: '',
      },
    };
  },

  computed: {
    availableDestinations() {
      return this.accounts.filter(a => a.account_id !== this.form.fromAccountId);
    },

    formattedAvailableBalance() {
      if (this.availableBalance === null) return 'Awaiting balance';
      return Number.parseFloat(this.availableBalance || 0).toLocaleString('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });
    },

    summaryHeadline() {
      if (!this.form.fromAccountId) return 'Select a source account';
      if (!this.form.toAccountId) return `Funding will leave ${this.form.fromAccountId}`;
      if (!this.form.amount) return `Route ready: ${this.form.fromAccountId} to ${this.form.toAccountId}`;
      return `Move $${this.formatAmountPreview(this.form.amount)} to ${this.form.toAccountId}`;
    },

    summaryLabel() {
      if (!this.form.fromAccountId) {
        return 'Choose the account that should fund this transfer.';
      }

      if (!this.form.toAccountId) {
        return `Select the destination account for funds leaving ${this.form.fromAccountId}.`;
      }

      if (!this.form.amount) {
        return `Enter the amount you want to move from ${this.form.fromAccountId}.`;
      }

      return `Prepared to move funds from ${this.form.fromAccountId} to ${this.form.toAccountId}.`;
    },

    validationError() {
      if (this.form.fromAccountId && this.form.fromAccountId === this.form.toAccountId) {
        return 'Cannot transfer to the same account';
      }
      if (this.form.amount && Number.parseFloat(this.form.amount) <= 0) {
        return 'Amount must be greater than zero';
      }
      return null;
    },

    isValid() {
      return (
        this.form.fromAccountId &&
        this.form.toAccountId &&
        this.form.amount &&
        Number.parseFloat(this.form.amount) > 0 &&
        this.form.fromAccountId !== this.form.toAccountId &&
        !this.validationError
      );
    },
  },

  watch: {
    selectedAccountId: {
      immediate: true,
      handler(newVal) {
        if (newVal) {
          this.form.fromAccountId = newVal;
        }
      },
    },

    'form.fromAccountId'(newVal) {
      if (newVal && this.form.toAccountId === newVal) {
        this.form.toAccountId = '';
      }
    },

    resetKey() {
      this.form.toAccountId = '';
      this.form.amount = '';
    },
  },

  methods: {
    onAmountInput(event) {
      const rawValue = event.target.value || '';
      const sanitized = rawValue
        .replace(/[^\d.]/g, '')
        .replace(/(\..*)\./g, '$1');

      const [wholePart = '', fractionPart = ''] = sanitized.split('.', 2);
      const normalizedWhole = wholePart.replace(/^0+(?=\d)/, '') || (wholePart ? '0' : '');
      const normalizedFraction = fractionPart.slice(0, 2);

      this.form.amount = sanitized.includes('.')
        ? `${normalizedWhole}.${normalizedFraction}`
        : normalizedWhole;
    },

    formatAmountPreview(amount) {
      const parsed = Number.parseFloat(amount);
      if (Number.isNaN(parsed)) {
        return '0.00';
      }

      return parsed.toFixed(2);
    },

    handleSubmit() {
      if (!this.isValid || this.loading) return;

      this.$emit('transfer', {
        fromAccountId: this.form.fromAccountId,
        toAccountId: this.form.toAccountId,
        amount: this.form.amount,
      });
    },
  },
};
</script>

<style scoped>
.transfer-intro {
  margin-bottom: 18px;
  color: var(--color-ink-soft);
  font-size: 0.92rem;
}

.transfer-context {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 16px 18px;
  margin-bottom: 18px;
  border-radius: 20px;
  background: rgba(15, 118, 110, 0.08);
  border: 1px solid rgba(15, 118, 110, 0.12);
}

.transfer-context-label,
.transfer-context-balance span,
.transfer-summary-label {
  display: block;
  margin-bottom: 6px;
  font-size: 0.74rem;
  font-weight: 800;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--color-primary);
}

.transfer-context strong,
.transfer-context-balance strong,
.transfer-summary strong {
  display: block;
  color: var(--color-ink);
}

.transfer-context p {
  color: var(--color-ink-soft);
  font-size: 0.86rem;
}

.transfer-context-balance {
  text-align: right;
}

.amount-field {
  position: relative;
}

.amount-field span {
  position: absolute;
  left: 16px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--color-ink-soft);
  font-weight: 700;
}

.amount-input {
  padding-left: 36px;
}

.transfer-summary {
  display: flex;
  flex-direction: column;
  gap: 2px;
  margin: 4px 0 16px;
  padding: 14px 16px;
  border-radius: 16px;
  background: rgba(15, 118, 110, 0.08);
  color: var(--color-primary);
  font-size: 0.86rem;
}

.transfer-summary span {
  color: var(--color-ink-soft);
}

@media (max-width: 640px) {
  .transfer-context {
    flex-direction: column;
    align-items: flex-start;
  }

  .transfer-context-balance {
    text-align: left;
  }
}
</style>
