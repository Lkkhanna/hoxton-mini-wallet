<template>
  <transition name="modal-fade">
    <div v-if="show && transaction" class="modal-overlay" @click.self="$emit('close')">
      <div class="modal-content card" id="transaction-details-modal">
        <div class="modal-header">
          <div>
            <p class="modal-kicker">Transaction details</p>
            <h2>{{ directionLabel }}</h2>
          </div>
          <button class="modal-close" @click="$emit('close')">&times;</button>
        </div>

        <div class="detail-hero" :class="transaction.type">
          <span class="detail-kind">{{ transaction.type }}</span>
          <strong>{{ signedAmount }}</strong>
          <p>{{ transaction.description || fallbackDescription }}</p>
        </div>

        <div class="detail-grid">
          <div class="detail-item">
            <span>Reference</span>
            <strong>{{ transaction.transaction_id }}</strong>
          </div>
          <div class="detail-item">
            <span>Viewing account</span>
            <strong>{{ accountId || 'N/A' }}</strong>
          </div>
          <div class="detail-item">
            <span>Counterparty</span>
            <strong>{{ transaction.counterparty || 'N/A' }}</strong>
          </div>
          <div class="detail-item">
            <span>Booked at</span>
            <strong>{{ formattedTimestamp }}</strong>
          </div>
        </div>

        <div class="detail-note">
          <span class="detail-note-label">Ledger note</span>
          <p>
            {{ transaction.type === 'credit'
              ? `This transaction increased the balance of ${accountId || 'the selected account'}.`
              : `This transaction reduced the balance of ${accountId || 'the selected account'}.` }}
          </p>
        </div>
      </div>
    </div>
  </transition>
</template>

<script>
export default {
  name: 'TransactionDetailsModal',
  props: {
    show: { type: Boolean, default: false },
    transaction: { type: Object, default: null },
    accountId: { type: String, default: null },
  },

  computed: {
    directionLabel() {
      if (!this.transaction) return 'Transaction';
      return this.transaction.type === 'credit' ? 'Incoming funds' : 'Outgoing funds';
    },

    signedAmount() {
      if (!this.transaction) return '$0.00';
      const formatted = Number.parseFloat(this.transaction.amount || 0).toLocaleString('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });

      return `${this.transaction.type === 'credit' ? '+' : '-'}${formatted}`;
    },

    formattedTimestamp() {
      if (!this.transaction?.timestamp) return 'N/A';
      const date = new Date(this.transaction.timestamp);
      return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
      });
    },

    fallbackDescription() {
      if (!this.transaction) return 'No detail available';
      return this.transaction.type === 'credit'
        ? `Funds received from ${this.transaction.counterparty}`
        : `Funds sent to ${this.transaction.counterparty}`;
    },
  },
};
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(4, 24, 41, 0.48);
  backdrop-filter: blur(10px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  width: 100%;
  max-width: 560px;
  margin: 20px;
  animation: modal-enter 0.2s ease-out;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
}

.modal-header h2 {
  font-family: var(--font-family-display);
  font-size: 2rem;
  line-height: 0.95;
  font-weight: 600;
}

.modal-kicker {
  font-size: 0.76rem;
  text-transform: uppercase;
  letter-spacing: 0.16em;
  color: var(--color-primary);
  margin-bottom: 6px;
}

.modal-close {
  background: rgba(255, 255, 255, 0.7);
  border: 1px solid rgba(13, 34, 56, 0.08);
  color: var(--color-ink-soft);
  font-size: 24px;
  cursor: pointer;
  padding: 4px 10px;
  border-radius: 999px;
  transition: var(--transition);
}

.modal-close:hover {
  color: var(--color-ink);
  background: #fff;
}

.detail-hero {
  padding: 18px 20px;
  border-radius: 22px;
  margin-bottom: 18px;
}

.detail-hero.credit {
  background: rgba(20, 125, 100, 0.1);
}

.detail-hero.debit {
  background: rgba(180, 83, 76, 0.1);
}

.detail-kind {
  display: inline-block;
  margin-bottom: 10px;
  padding: 5px 10px;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  background: rgba(255, 255, 255, 0.72);
}

.detail-hero strong {
  display: block;
  font-family: var(--font-family-display);
  font-size: 2.6rem;
  line-height: 0.95;
  margin-bottom: 8px;
}

.detail-hero p,
.detail-note p {
  color: var(--color-ink-soft);
}

.detail-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
  margin-bottom: 18px;
}

.detail-item {
  padding: 14px 16px;
  border-radius: 18px;
  background: rgba(255, 255, 255, 0.72);
  border: 1px solid rgba(13, 34, 56, 0.08);
}

.detail-item span,
.detail-note-label {
  display: block;
  margin-bottom: 6px;
  color: var(--color-ink-soft);
  font-size: 0.74rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.detail-item strong {
  color: var(--color-ink);
  word-break: break-word;
}

.detail-note {
  padding: 16px 18px;
  border-radius: 18px;
  background: rgba(15, 118, 110, 0.06);
  border: 1px solid rgba(15, 118, 110, 0.1);
}

.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.2s ease;
}

.modal-fade-enter,
.modal-fade-leave-to {
  opacity: 0;
}

@keyframes modal-enter {
  from {
    opacity: 0;
    transform: scale(0.95) translateY(-10px);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}

@media (max-width: 640px) {
  .detail-grid {
    grid-template-columns: 1fr;
  }

  .detail-hero strong {
    font-size: 2.2rem;
  }
}
</style>
