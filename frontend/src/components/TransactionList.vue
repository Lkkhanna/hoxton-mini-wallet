<template>
  <div class="card transaction-card" id="transaction-list">
    <div class="card-title">
      Transaction history
      <span v-if="pagination.total" class="tx-count">{{ pagination.total }}</span>
    </div>

    <div v-if="!accountId" class="empty-state">
      <div class="empty-icon">01</div>
      <p>Select an account to unlock the activity feed.</p>
    </div>

    <div v-else-if="loading" class="loading-container">
      <span class="spinner"></span>
      <span>Loading transactions...</span>
    </div>

    <div v-else-if="error" class="error-message">
      {{ error }}
    </div>

    <div v-else-if="transactions.length === 0" class="empty-state">
      <div class="empty-icon">0</div>
      <p>No transactions to display.</p>
    </div>

    <div v-else>
      <div class="tx-list">
        <div
          v-for="tx in transactions"
          :key="tx.id"
          class="tx-item"
          :class="tx.type"
          role="button"
          tabindex="0"
          @click="$emit('transaction-selected', tx)"
          @keydown.enter.prevent="$emit('transaction-selected', tx)"
          @keydown.space.prevent="$emit('transaction-selected', tx)"
        >
          <div class="tx-left">
            <div class="tx-type-badge" :class="tx.type">
              {{ tx.type === 'credit' ? '↓' : '↑' }}
            </div>
            <div class="tx-details">
              <div class="tx-description">{{ tx.description || formatDescription(tx) }}</div>
              <div class="tx-meta">
                <span class="tx-counterparty">{{ tx.type === 'credit' ? 'From' : 'To' }} {{ tx.counterparty }}</span>
                <span class="tx-dot">·</span>
                <span class="tx-id" :title="tx.transaction_id">{{ truncateId(tx.transaction_id) }}</span>
                <span class="tx-dot">·</span>
                <span class="tx-time">{{ formatTime(tx.timestamp) }}</span>
              </div>
            </div>
          </div>
          <div class="tx-right">
            <span class="tx-kind" :class="tx.type">{{ tx.type }}</span>
            <div class="tx-amount" :class="tx.type">
              {{ tx.type === 'credit' ? '+' : '-' }}${{ formatAmount(tx.amount) }}
            </div>
          </div>
        </div>
      </div>

      <div v-if="pagination.lastPage > 1" class="tx-pagination">
        <div class="tx-pagination-summary">
          Showing {{ pagination.from }}-{{ pagination.to }} of {{ pagination.total }}
        </div>
        <div class="tx-pagination-actions">
          <button
            class="btn btn-secondary btn-sm"
            :disabled="pagination.currentPage <= 1"
            @click="$emit('page-change', pagination.currentPage - 1)"
          >
            Previous
          </button>
          <span class="tx-page-indicator">
            Page {{ pagination.currentPage }} of {{ pagination.lastPage }}
          </span>
          <button
            class="btn btn-secondary btn-sm"
            :disabled="pagination.currentPage >= pagination.lastPage"
            @click="$emit('page-change', pagination.currentPage + 1)"
          >
            Next
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'TransactionList',
  props: {
    accountId: { type: String, default: null },
    transactions: { type: Array, default: () => [] },
    pagination: {
      type: Object,
      default: () => ({
        currentPage: 1,
        lastPage: 1,
        perPage: 10,
        total: 0,
        from: null,
        to: null,
      }),
    },
    loading: { type: Boolean, default: false },
    error: { type: String, default: null },
  },

  methods: {
    formatAmount(amount) {
      return parseFloat(amount).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });
    },

    formatTime(timestamp) {
      const date = new Date(timestamp);
      const now = new Date();
      const diffMs = now - date;
      const diffMins = Math.floor(diffMs / 60000);
      const diffHours = Math.floor(diffMs / 3600000);
      const diffDays = Math.floor(diffMs / 86400000);

      if (diffMins < 1) return 'Just now';
      if (diffMins < 60) return `${diffMins}m ago`;
      if (diffHours < 24) return `${diffHours}h ago`;
      if (diffDays < 7) return `${diffDays}d ago`;
      return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    },

    truncateId(id) {
      if (!id) return '';
      if (id.length <= 12) return id;
      return id.substring(0, 8) + '...';
    },

    formatDescription(tx) {
      if (tx.type === 'credit') {
        return `Received from ${tx.counterparty}`;
      }
      return `Sent to ${tx.counterparty}`;
    },
  },
};
</script>

<style scoped>
.transaction-card {
  min-height: 300px;
}

.tx-count {
  background: var(--color-secondary-soft);
  color: var(--color-primary);
  padding: 4px 10px;
  border-radius: var(--radius-pill);
  font-size: 0.78rem;
  margin-left: auto;
}

.empty-state {
  text-align: center;
  padding: 48px 20px;
  color: var(--color-muted);
}

.empty-icon {
  width: 62px;
  height: 62px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  margin: 0 auto 12px;
  background: rgba(15, 118, 110, 0.08);
  color: var(--color-primary);
  font-size: 1.2rem;
  font-weight: 800;
  margin-bottom: 12px;
}

.empty-state p {
  font-size: 0.92rem;
}

.tx-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.tx-item {
  display: flex;
  align-items: center;
  gap: 16px;
  justify-content: space-between;
  padding: 16px 18px;
  border-radius: 20px;
  transition: var(--transition);
  border: 1px solid rgba(13, 34, 56, 0.08);
  background: rgba(255, 255, 255, 0.58);
  cursor: pointer;
  outline: none;
}

.tx-item:hover,
.tx-item:focus {
  background: rgba(255, 255, 255, 0.85);
  border-color: rgba(13, 34, 56, 0.14);
  box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.08);
}

.tx-left {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  min-width: 0;
  flex: 1;
}

.tx-type-badge {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  font-weight: 700;
  flex-shrink: 0;
}

.tx-type-badge.credit {
  background: var(--color-success-bg);
  color: var(--color-success);
}

.tx-type-badge.debit {
  background: var(--color-danger-bg);
  color: var(--color-danger);
}

.tx-details {
  min-width: 0;
}

.tx-description {
  font-size: 0.98rem;
  font-weight: 700;
  color: var(--color-ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.tx-meta {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 2px;
  flex-wrap: wrap;
}

.tx-counterparty {
  font-size: 0.74rem;
  color: var(--color-ink-soft);
  font-weight: 700;
}

.tx-id {
  font-size: 0.74rem;
  color: var(--color-muted);
  font-family: monospace;
}

.tx-dot {
  color: var(--color-muted);
  font-size: 10px;
}

.tx-time {
  font-size: 0.74rem;
  color: var(--color-muted);
}

.tx-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 8px;
  margin-left: 16px;
}

.tx-kind {
  padding: 5px 10px;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.tx-kind.credit {
  background: var(--color-success-bg);
  color: var(--color-success);
}

.tx-kind.debit {
  background: var(--color-danger-bg);
  color: var(--color-danger);
}

.tx-amount {
  font-size: 1.08rem;
  font-weight: 800;
  white-space: nowrap;
}

.tx-amount.credit {
  color: var(--color-success);
}

.tx-amount.debit {
  color: var(--color-danger);
}

.tx-pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-top: 18px;
  padding-top: 18px;
  border-top: 1px solid rgba(13, 34, 56, 0.08);
}

.tx-pagination-summary,
.tx-page-indicator {
  font-size: 0.82rem;
  color: var(--color-muted);
}

.tx-pagination-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

@media (max-width: 640px) {
  .tx-item {
    flex-direction: column;
    align-items: flex-start;
  }

  .tx-right {
    align-items: flex-start;
    margin-left: 48px;
  }

  .tx-pagination,
  .tx-pagination-actions {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
