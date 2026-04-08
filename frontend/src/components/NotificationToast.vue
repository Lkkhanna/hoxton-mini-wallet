<template>
  <transition name="toast-slide">
    <div
      v-if="show"
      class="toast"
      :class="type"
      id="notification-toast"
      @click="$emit('close')"
    >
      <span class="toast-badge">{{ badgeLabel }}</span>
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
    type: { type: String, default: 'info' },
  },

  computed: {
    badgeLabel() {
      switch (this.type) {
        case 'success': return 'Success';
        case 'error': return 'Alert';
        default: return 'Update';
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
  gap: 12px;
  padding: 14px 18px;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  z-index: 2000;
  max-width: 450px;
  box-shadow: 0 18px 38px rgba(8, 26, 44, 0.18);
  backdrop-filter: blur(12px);
  border: 1px solid;
}

.toast.success {
  background: rgba(250, 252, 246, 0.9);
  border-color: rgba(20, 125, 100, 0.2);
  color: var(--color-success);
}

.toast.error {
  background: rgba(255, 249, 247, 0.92);
  border-color: rgba(180, 83, 76, 0.22);
  color: var(--color-danger);
}

.toast.info {
  background: rgba(255, 252, 246, 0.92);
  border-color: rgba(15, 118, 110, 0.2);
  color: var(--color-primary);
}

.toast-badge {
  padding: 5px 10px;
  border-radius: 999px;
  font-size: 0.72rem;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  background: rgba(255, 255, 255, 0.6);
  border: 1px solid currentColor;
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
