// assets/js/main.js

class VTUApp {
  constructor() {
    this.initPINModal();
    this.initFormHelpers();
    this.initCSRF();
  }

  // === PIN Modal ===
  initPINModal() {
    this.pinModal = document.getElementById('pinModal');
    this.pinInput = document.getElementById('pinInput');
    this.actionType = '';

    if (this.pinModal) {
      // Show modal
      window.showPinModal = (type) => {
        this.actionType = type;
        this.pinModal.style.display = 'flex';
        this.pinInput.focus();
      };

      // Hide modal
      window.hidePinModal = () => {
        this.pinModal.style.display = 'none';
        this.pinInput.value = '';
      };

      // Verify PIN
      window.verifyPin = () => {
        const pin = this.pinInput.value;
        if (pin.length !== 4 || !/^\d+$/.test(pin)) {
          alert('PIN must be 4 digits');
          return;
        }

        fetch('user/verify_pin.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `pin=${encodeURIComponent(pin)}&_token=${this.csrfToken}`
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            this.hidePinModal();
            if (this.actionType === 'fund') {
              window.location.href = 'user/fund_wallet.php';
            } else if (this.actionType === 'data') {
              window.location.href = 'user/buy_data.php';
            } else if (this.actionType === 'bulk') {
              window.location.href = 'user/bulk_buy.php';
            }
          } else {
            alert('Invalid PIN. Please try again.');
            this.pinInput.value = '';
            this.pinInput.focus();
          }
        })
        .catch(err => {
          alert('Error verifying PIN. Try again.');
        });
      };

      // Close on Esc
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') this.hidePinModal();
      });
    }
  }

  // === Form Helpers ===
  initFormHelpers() {
    // Auto-format phone numbers
    const phoneInputs = document.querySelectorAll('input[type="tel"]');
    phoneInputs.forEach(input => {
      input.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 11) value = value.substring(0, 11);
        e.target.value = value.replace(/(\d{3})(\d{3})(\d{4})/, '$1 $2 $3').trim();
      });
    });

    // Numeric input
    const numericInputs = document.querySelectorAll('input[type="number"]');
    numericInputs.forEach(input => {
      input.addEventListener('keypress', function(e) {
        if (!/[0-9.]/.test(e.key) && e.key !== 'Backspace' && e.key !== 'Delete' && e.key !== 'Tab') {
          e.preventDefault();
        }
      });
    });
  }

  // === CSRF Protection ===
  initCSRF() {
    this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  }

  // === Utility ===
  hidePinModal() {
    if (this.pinModal) {
      this.pinModal.style.display = 'none';
      this.pinInput.value = '';
    }
  }
}

// Initialize on DOMContentLoaded
document.addEventListener('DOMContentLoaded', () => {
  window.vtuApp = new VTUApp();
});