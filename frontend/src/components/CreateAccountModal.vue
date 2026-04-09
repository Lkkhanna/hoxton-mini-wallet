<template>
  <transition name="modal-fade">
      <div v-if="show" class="modal-overlay" @click.self="$emit('close')">
      <div class="modal-content card" id="create-account-modal">
        <div class="modal-header">
          <div>
            <p class="modal-kicker">Open relationship</p>
            <h2>Create New Account</h2>
          </div>
          <button class="modal-close" @click="$emit('close')">&times;</button>
        </div>

        <form @submit.prevent="handleSubmit">
          <div class="form-group">
            <label for="new-account-id">Account ID</label>
            <input
              id="new-account-id"
              type="text"
              class="form-input"
              v-model="form.accountId"
              placeholder="e.g. ACC004"
              minlength="3"
              maxlength="10"
              required
              ref="accountIdInput"
              @input="clearAccountIdValidity"
            />
            <small class="form-hint">Letters, numbers, hyphens, and underscores only</small>
          </div>

          <div class="form-group">
            <label for="new-account-name">Name (optional)</label>
            <input
              id="new-account-name"
              type="text"
              class="form-input"
              v-model="form.name"
              placeholder="e.g. John Doe"
              maxlength="100"
            />
          </div>

          <div class="modal-actions">
            <button
              type="button"
              class="btn btn-secondary"
              @click="$emit('close')"
              :disabled="loading"
            >
              Cancel
            </button>
            <button
              id="btn-submit-create"
              type="submit"
              class="btn btn-primary"
              :disabled="!form.accountId.trim() || loading"
            >
              <span v-if="loading" class="spinner" style="border-top-color: white;"></span>
              {{ loading ? 'Creating...' : 'Create Account' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </transition>
</template>

<script>
export default {
  // The modal keeps account creation lightweight, while canonicalization stays
  // consistent with the backend by uppercasing the account_id before submit.
  name: 'CreateAccountModal',
  props: {
    show: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
  },

  data() {
    return {
      form: {
        accountId: '',
        name: '',
      },
    };
  },

  watch: {
    // Reset modal state on each open so stale input never leaks between attempts.
    show(newVal) {
      if (newVal) {
        this.form.accountId = '';
        this.form.name = '';
        this.$nextTick(() => {
          if (this.$refs.accountIdInput) {
            this.$refs.accountIdInput.focus();
          }
        });
      }
    },
  },

  methods: {
    clearAccountIdValidity() {
      this.$refs.accountIdInput?.setCustomValidity('');
    },

    // Keep the emitted payload canonical and trimmed so the view and API are
    // working with the same account identifier format.
    handleSubmit() {
      const normalizedAccountId = this.form.accountId.trim().toUpperCase();

      if (!normalizedAccountId || this.loading) return;

      if (normalizedAccountId.length < 3) {
        this.$refs.accountIdInput?.setCustomValidity(
          'Account ID must be at least 3 characters.'
        );
        this.$refs.accountIdInput?.reportValidity();
        return;
      }

      if (!/^[A-Z0-9_-]+$/.test(normalizedAccountId)) {
        this.$refs.accountIdInput?.setCustomValidity(
          'Use letters, numbers, hyphens, or underscores only.'
        );
        this.$refs.accountIdInput?.reportValidity();
        return;
      }

      this.$refs.accountIdInput?.setCustomValidity('');

      this.$emit('create', {
        accountId: normalizedAccountId,
        name: this.form.name.trim() || null,
      });
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
  max-width: 440px;
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

.modal-actions {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
  margin-top: 24px;
}

.form-hint {
  display: block;
  margin-top: 4px;
  font-size: 0.8rem;
  color: var(--color-muted);
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
</style>
