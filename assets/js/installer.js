// assets/js/installer.js

class InstallerApp {
  constructor() {
    this.initProgress();
    this.initBackButtons();
    this.initFormValidation();
  }

  // === Progress Bar Animation ===
  initProgress() {
    const currentStep = document.querySelector('.step.current');
    if (currentStep) {
      const stepNumber = parseInt(currentStep.textContent);
      const progressPercent = (stepNumber / 4) * 100;
      
      const progressBar = document.querySelector('.progress-bar');
      if (progressBar) {
        // Animate progress bar
        setTimeout(() => {
          progressBar.style.width = `${progressPercent}%`;
        }, 100);
      }
    }
  }

  // === Back Button Handling ===
  initBackButtons() {
    const backButtons = document.querySelectorAll('.btn-back');
    backButtons.forEach(button => {
      button.addEventListener('click', (e) => {
        e.preventDefault();
        
        // Show confirmation if form has data
        const form = button.closest('form');
        if (form && this.formHasData(form)) {
          if (!confirm('You have unsaved changes. Are you sure you want to go back?')) {
            return;
          }
        }
        
        window.location.href = button.href;
      });
    });
  }

  // === Form Validation ===
  initFormValidation() {
    // DB config form
    const dbForm = document.getElementById('dbForm');
    if (dbForm) {
      dbForm.addEventListener('submit', (e) => {
        const dbName = dbForm.querySelector('[name="db_name"]').value.trim();
        const dbUser = dbForm.querySelector('[name="db_user"]').value.trim();
        
        if (!dbName || !dbUser) {
          e.preventDefault();
          alert('Database name and username are required.');
          return false;
        }
      });
    }

    // Admin form
    const adminForm = document.getElementById('adminForm');
    if (adminForm) {
      adminForm.addEventListener('submit', (e) => {
        const password = adminForm.querySelector('[name="password"]').value;
        const pin = adminForm.querySelector('[name="pin"]').value;
        
        if (password.length < 8) {
          e.preventDefault();
          alert('Password must be at least 8 characters.');
          return false;
        }
        
        if (!/^\d{4}$/.test(pin)) {
          e.preventDefault();
          alert('Security PIN must be exactly 4 digits.');
          return false;
        }
      });
    }
  }

  // === Helper ===
  formHasData(form) {
    const inputs = form.querySelectorAll('input, select, textarea');
    for (let input of inputs) {
      if (input.value && input.value.trim() !== '') {
        return true;
      }
    }
    return false;
  }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  new InstallerApp();
});