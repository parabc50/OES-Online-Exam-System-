/**
 * Online Examination System
 * Main JavaScript File
 */

// Utility Functions

/**
 * Show/hide loading spinner
 */
function showLoading(show = true) {
    const loader = document.getElementById('loader');
    if (loader) {
        loader.style.display = show ? 'block' : 'none';
    }
}

/**
 * Confirm action
 */
function confirmAction(message = 'Are you sure?') {
    return confirm(message);
}

/**
 * Format date
 */
function formatDate(dateString) {
    const options = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
    return new Date(dateString).toLocaleDateString('en-US', options);
}

/**
 * Validate email
 */
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

/**
 * Clear form
 */
function clearForm(formId) {
    document.getElementById(formId).reset();
}

/**
 * Hide error messages
 */
function hideErrors() {
    const alerts = document.querySelectorAll('.alert-error');
    alerts.forEach(alert => alert.style.display = 'none');
}

/**
 * Show notification
 */
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    notification.textContent = message;
    document.body.insertBefore(notification, document.body.firstChild);
    
    setTimeout(() => notification.remove(), 5000);
}

/**
 * Format time (MM:SS)
 */
function formatTime(seconds) {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
}

/**
 * Prevent page close warning
 */
function setPageCloseWarning(enable = true) {
    if (enable) {
        window.onbeforeunload = (e) => {
            e.preventDefault();
            e.returnValue = '';
            return '';
        };
    } else {
        window.onbeforeunload = null;
    }
}

/**
 * Enable/disable form inputs
 */
function setFormDisabled(formId, disabled = true) {
    const form = document.getElementById(formId);
    if (form) {
        const inputs = form.querySelectorAll('input, select, textarea, button');
        inputs.forEach(input => input.disabled = disabled);
    }
}

/**
 * Get form data as object
 */
function getFormData(formId) {
    const form = document.getElementById(formId);
    const formData = new FormData(form);
    const data = {};
    
    formData.forEach((value, key) => {
        if (data.hasOwnProperty(key)) {
            if (!Array.isArray(data[key])) {
                data[key] = [data[key]];
            }
            data[key].push(value);
        } else {
            data[key] = value;
        }
    });
    
    return data;
}

/**
 * Format currency
 */
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(amount);
}

/**
 * Array of unique items
 */
function getUnique(array) {
    return [...new Set(array)];
}

/**
 * Debounce function
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Throttle function
 */
function throttle(func, limit) {
    let inThrottle;
    return function(...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

// Exam-specific Functions

/**
 * Start exam timer
 */
function startExamTimer(durationMinutes, onTimeUp, warningThreshold = 300) {
    let timeInSeconds = durationMinutes * 60;
    
    const updateTimer = () => {
        const minutes = Math.floor(timeInSeconds / 60);
        const seconds = timeInSeconds % 60;
        const timerDisplay = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        
        const timerElement = document.getElementById('timer');
        if (timerElement) {
            timerElement.textContent = timerDisplay;
            
            if (timeInSeconds <= warningThreshold) {
                timerElement.style.color = '#e74c3c';
            }
        }
        
        if (timeInSeconds <= 0) {
            clearInterval(timerInterval);
            if (onTimeUp) onTimeUp();
        }
        
        timeInSeconds--;
    };
    
    const timerInterval = setInterval(updateTimer, 1000);
    updateTimer(); // Call immediately
    
    return timerInterval;
}

/**
 * Validate exam answers before submission
 */
function validateExamAnswers() {
    const answers = document.querySelectorAll('[name^="question_"]');
    let answered = 0;
    
    answers.forEach(answer => {
        if (answer.value) {
            answered++;
        }
    });
    
    return {
        total: answers.length,
        answered: answered,
        unanswered: answers.length - answered
    };
}

/**
 * Auto-save exam answers
 */
function autoSaveAnswers(formId, saveUrl) {
    const form = document.getElementById(formId);
    
    form.addEventListener('change', debounce(() => {
        const formData = new FormData(form);
        
        fetch(saveUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Answers auto-saved');
            }
        })
        .catch(error => console.error('Auto-save error:', error));
    }, 5000));
}

// Document Ready Events
document.addEventListener('DOMContentLoaded', function() {
    // Add keyboard shortcuts for accessibility
    document.addEventListener('keydown', function(e) {
        // Ctrl+S - Save (prevent browser default)
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
        }
    });
});

// Export functions for use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        showLoading,
        confirmAction,
        formatDate,
        validateEmail,
        clearForm,
        hideErrors,
        showNotification,
        formatTime,
        setPageCloseWarning,
        setFormDisabled,
        getFormData,
        formatCurrency,
        getUnique,
        debounce,
        throttle,
        startExamTimer,
        validateExamAnswers,
        autoSaveAnswers
    };
}
