<template>
  <div class="card" id="transfer-form">
    <div class="card-title">
      <span>💸</span> Transfer Money
    </div>

    <form @submit.prevent="handleSubmit">
      <!-- From Account -->
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

      <!-- To Account -->
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

      <!-- Amount -->
      <div class="form-group">
        <label for="transfer-amount">Amount ($)</label>
        <input
          id="transfer-amount"
          type="number"
          class="form-input"
          v-model="form.amount"
          min="0.01"
          step="0.01"
          placeholder="0.00"
          required
        />
      </div>

      <!-- Validation Message -->
      <div v-if="validationError" class="error-message" style="margin-bottom: 16px;">
        {{ validationError }}
      </div>

      <!-- Submit -->
      <button
        id="btn-transfer"
        type="submit"
        class="btn btn-primary btn-full"
        :disabled="!isValid || loading"
      >
        <span v-if="loading" class="spinner" style="border-top-color: white;"></span>
        <span v-else>⚡</span>
        {{ loading ? 'Processing...' : 'Send Transfer' }}
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
    // Filter out the source account from destinations (prevent self-transfer)
    availableDestinations() {
      return this.accounts.filter(a => a.account_id !== this.form.fromAccountId);
    },

    validationError() {
      if (this.form.fromAccountId && this.form.fromAccountId === this.form.toAccountId) {
        return 'Cannot transfer to the same account';
      }
      if (this.form.amount && parseFloat(this.form.amount) <= 0) {
        return 'Amount must be greater than zero';
      }
      return null;
    },

    isValid() {
      return (
        this.form.fromAccountId &&
        this.form.toAccountId &&
        this.form.amount &&
        parseFloat(this.form.amount) > 0 &&
        this.form.fromAccountId !== this.form.toAccountId &&
        !this.validationError
      );
    },
  },

  watch: {
    // Auto-fill "from" when a global account is selected
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
/* Transfer form inherits global form styles */
</style>
