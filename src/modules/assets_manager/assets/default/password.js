document.querySelectorAll('.password-wrapper').forEach(wrapper => {
    const input = wrapper.querySelector('input.password');
    const toggle = wrapper.querySelector('.toggle-password-icon');
    const strengthBar = wrapper.parentElement.querySelector('.strength-bar');
    const strengthText = wrapper.parentElement.querySelector('.strength-text');

    // Show/hide password toggle
    toggle.addEventListener('click', () => {
        input.type = input.type === 'password' ? 'text' : 'password';
        toggle.classList.toggle('fa-eye');
        toggle.classList.toggle('fa-eye-slash');
    });

    // Password strength checker
    input.addEventListener('input', () => {
        const val = input.value;
        let score = 0;
        if (val.length >= 8) score++;
        if (/[a-z]/.test(val)) score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        const bar = strengthBar;
        const text = strengthText;

        bar.style.height = '5px';
        bar.style.marginTop = '5px';
        bar.style.borderRadius = '3px';
        bar.style.transition = 'all 0.3s ease';

        const colors = ['#ccc', 'red', 'orange', 'gold', 'limegreen'];
        const messages = ['Too Weak', 'Weak', 'Fair', 'Good', 'Strong'];

        bar.style.width = (score * 20) + '%';
        bar.style.backgroundColor = colors[score] || '#ccc';
        text.textContent = messages[score] || '';
        text.style.color = colors[score] || '#ccc';
    });
});
