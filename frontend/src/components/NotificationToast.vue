<template>
  <transition name="toast-slide">
    <div
      v-if="show"
      class="toast"
      :class="type"
      id="notification-toast"
      @click="$emit('close')"
    >
      <span class="toast-icon">{{ icon }}</span>
      <span class="toast-message">{{ message }}</span>
      <button class="toast-close">&times;</button>
    </div>
  </transition>
</template>

<script>
export default {
  name: 'NotificationToast',
  props: {
    show: { type: Boolean, default: false },
    message: { type: String, default: '' },
    type: { type: String, default: 'info' }, // 'success' | 'error' | 'info'
  },

  computed: {
    icon() {
      switch (this.type) {
        case 'success': return '✅';
        case 'error':   return '❌';
        default:        return 'ℹ️';
      }
    },
  },
};
</script>

<style scoped>
.toast {
  position: fixed;
  top: 24px;
  right: 24px;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px 20px;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  z-index: 2000;
  max-width: 450px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
  backdrop-filter: blur(12px);
  border: 1px solid;
}

.toast.success {
  background: rgba(16, 185, 129, 0.15);
  border-color: rgba(16, 185, 129, 0.3);
  color: #34d399;
}

.toast.error {
  background: rgba(239, 68, 68, 0.15);
  border-color: rgba(239, 68, 68, 0.3);
  color: #f87171;
}

.toast.info {
  background: rgba(99, 102, 241, 0.15);
  border-color: rgba(99, 102, 241, 0.3);
  color: #a5b4fc;
}

.toast-icon {
  font-size: 16px;
  flex-shrink: 0;
}

.toast-message {
  flex: 1;
}

.toast-close {
  background: none;
  border: none;
  color: inherit;
  font-size: 18px;
  cursor: pointer;
  opacity: 0.6;
  padding: 0 4px;
  flex-shrink: 0;
}

.toast-close:hover {
  opacity: 1;
}

/* ─── Transition ───────────────────────────────────────────────── */
.toast-slide-enter-active {
  transition: all 0.3s ease-out;
}

.toast-slide-leave-active {
  transition: all 0.2s ease-in;
}

.toast-slide-enter {
  opacity: 0;
  transform: translateX(100px);
}

.toast-slide-leave-to {
  opacity: 0;
  transform: translateY(-20px);
}
</style>
