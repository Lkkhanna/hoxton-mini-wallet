<template>
  <transition name="modal-fade">
    <div v-if="show" class="modal-overlay" @click.self="$emit('close')">
      <div class="modal-content card" id="create-account-modal">
        <div class="modal-header">
          <h2>Create New Account</h2>
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
              maxlength="50"
              pattern="[A-Za-z0-9_-]+"
              required
              ref="accountIdInput"
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
    handleSubmit() {
      if (!this.form.accountId.trim() || this.loading) return;

      this.$emit('create', {
        accountId: this.form.accountId.trim(),
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
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
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
  font-size: 18px;
  font-weight: 600;
}

.modal-close {
  background: none;
  border: none;
  color: var(--color-text-secondary);
  font-size: 24px;
  cursor: pointer;
  padding: 4px 8px;
  border-radius: 4px;
  transition: var(--transition);
}

.modal-close:hover {
  color: var(--color-text);
  background: var(--color-bg-card-hover);
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
  font-size: 12px;
  color: var(--color-text-muted);
}

/* ─── Transition ───────────────────────────────────────────────── */
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
