<template>
  <div class="card" id="account-selector">
    <div class="card-title">
      <span>👤</span> Select Account
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-container">
      <span class="spinner"></span>
      <span>Loading accounts...</span>
    </div>

    <!-- Account List -->
    <div v-else>
      <div class="form-group">
        <select
          id="account-dropdown"
          class="form-select"
          :value="selectedAccountId"
          @change="$emit('account-selected', $event.target.value)"
        >
          <option value="" disabled>Choose an account...</option>
          <option
            v-for="account in accounts"
            :key="account.account_id"
            :value="account.account_id"
          >
            {{ account.account_id }}{{ account.name ? ` — ${account.name}` : '' }}
          </option>
        </select>
      </div>

      <div class="account-actions">
        <button
          id="btn-create-account"
          class="btn btn-secondary btn-sm"
          @click="$emit('create-account')"
        >
          <span>+</span> New Account
        </button>
        <span class="account-count">{{ accounts.length }} account{{ accounts.length !== 1 ? 's' : '' }}</span>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'AccountSelector',
  props: {
    accounts: { type: Array, default: () => [] },
    selectedAccountId: { type: String, default: null },
    loading: { type: Boolean, default: false },
  },
};
</script>

<style scoped>
.account-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 4px;
}

.account-count {
  font-size: 12px;
  color: var(--color-text-muted);
}
</style>
