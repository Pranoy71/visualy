document.addEventListener('DOMContentLoaded', function() {
    const monospaceToggle = document.getElementById('monospace-toggle');
    const textOutput = document.getElementById('text-output');
    
    if (monospaceToggle && textOutput) {
        const savedMonospace = localStorage.getItem('monospaceView') === 'true';
        if (savedMonospace) {
            monospaceToggle.checked = true;
            textOutput.classList.add('monospace');
        }
        
        monospaceToggle.addEventListener('change', function() {
            if (this.checked) {
                textOutput.classList.add('monospace');
                localStorage.setItem('monospaceView', 'true');
            } else {
                textOutput.classList.remove('monospace');
                localStorage.setItem('monospaceView', 'false');
            }
        });
    }
    
    const form = document.querySelector('.compare-form');
    if (form) {
        form.addEventListener('submit', function() {
            setTimeout(() => {
                const results = document.querySelector('.results');
                if (results) {
                    results.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }, 100);
        });
    }
});

function resetForm() {
    const aText = document.getElementById('a_text');
    const bText = document.getElementById('b_text');
    
    if (aText && bText) {
        aText.value = '';
        bText.value = '';
        
        const monospaceToggle = document.getElementById('monospace-toggle');
        if (monospaceToggle) {
            monospaceToggle.checked = false;
        }
        
        const resultsSection = document.querySelector('.results');
        if (resultsSection) {
            resultsSection.style.display = 'none';
        }
        
        const infoSection = document.querySelector('.info-section');
        if (infoSection) {
            infoSection.style.display = 'none';
        }
        
        aText.focus();
        showNotification('✅ Form reset successfully', 'success');
    }
}

function copyToClipboard() {
    const textOutput = document.getElementById('text-output');
    if (!textOutput) return;
    
    const text = textOutput.innerText;
    
    navigator.clipboard.writeText(text).then(() => {
        showNotification('📋 Copied to clipboard!', 'success');
    }).catch(() => {
        showNotification('❌ Failed to copy text', 'error');
    });
}

function downloadAsFile() {
    const textOutput = document.getElementById('text-output');
    if (!textOutput) return;
    
    const text = textOutput.innerText;
    const timestamp = new Date().toISOString().split('T')[0];
    const filename = `diff-result-${timestamp}.txt`;
    
    const element = document.createElement('a');
    element.setAttribute('href', 'data:text/plain;charset=utf-8,' + encodeURIComponent(text));
    element.setAttribute('download', filename);
    element.style.display = 'none';
    
    document.body.appendChild(element);
    element.click();
    document.body.removeChild(element);
    
    showNotification(`💾 Downloaded as ${filename}`, 'success');
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    const bgColor = type === 'success' ? '#C4A662' : type === 'error' ? '#D97706' : '#B8956A';
    
    notification.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        padding: 16px 24px;
        background: ${bgColor};
        color: #F5EFE7;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(29, 11, 5, 0.3);
        z-index: 9999;
        animation: slideIn 0.3s ease;
        font-weight: 500;
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}

const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);
