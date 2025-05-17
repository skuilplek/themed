// new_password.js
// Initializes password input components with the given instance ID
function initPasswordComponent(instanceId, config = {}) {
    const pwdInput = document.getElementById(instanceId);
    const showHideBtn = document.getElementById(`showHideBtn-${instanceId}`);
    const createPwdBtn = document.getElementById(`createPwdBtn-${instanceId}`);
    const configBtn = document.getElementById(`configBtn-${instanceId}`);
    const configPanel = document.getElementById(`configPanel-${instanceId}`);
    const pwdStrengthBar = document.getElementById(`pwdStrengthBar-${instanceId}`)?.children;
    const errorList = document.getElementById(`errorList-${instanceId}`);
    const pwdLength = document.getElementById(`pwdLength-${instanceId}`);
    const letterTypeRadios = document.getElementsByName(`letterType-${instanceId}`);
    const extraTypeRadios = document.getElementsByName(`extraType-${instanceId}`);

    // Store showPassword state in a data attribute to support multiple instances
    pwdInput.dataset.showPassword = 'false';

    // Custom password generator
    function generatePassword(options) {
        const lowercase = 'abcdefghijklmnopqrstuvwxyz';
        const uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        const numbers = '0123456789';
        const symbols = '!@#$%^&*()_+-=[]{}|;:,.<>?';
        let chars = '';
        let password = '';

        // Build character set based on options
        if (options.lowercase) chars += lowercase;
        if (options.uppercase) chars += uppercase;
        if (options.numbers) chars += numbers;
        if (options.symbols) chars += symbols;

        // Ensure at least one character from each selected type
        if (options.lowercase) password += lowercase[Math.floor(Math.random() * lowercase.length)];
        if (options.uppercase) password += uppercase[Math.floor(Math.random() * uppercase.length)];
        if (options.numbers) password += numbers[Math.floor(Math.random() * numbers.length)];
        if (options.symbols) password += symbols[Math.floor(Math.random() * symbols.length)];

        // Fill the rest of the password length
        for (let i = password.length; i < options.length; i++) {
            password += chars[Math.floor(Math.random() * chars.length)];
        }

        // Shuffle the password
        password = password.split('').sort(() => Math.random() - 0.5).join('');
        return password;
    }

    function updateStrengthBar(score) {
        if (pwdStrengthBar) {
            for (let i = 0; i < pwdStrengthBar.length; i++) {
                pwdStrengthBar[i].className = score >= i ? `level-${score}` : '';
            }
        }
    }

    function validatePassword() {
        const password = pwdInput.value;
        const result = window.zxcvbn(password);
        const minLength = pwdInput.min || 10; // Consistent with Twig template's min="10"
        const minStrength = pwdInput.minStrength || 3; // zxcvbn score (0-4)
        let errors = [];

        if (password.length > 0 && password.length < minLength) {
            errors.push(`Enter a password that contains at least ${minLength} characters.`);
        }
        if (result.score < minStrength) {
            errors.push(`Enter a <strong>stronger</strong> password.`);
        }

        errorList.innerHTML = errors.map(err => `<li class="errorMsg-${instanceId}">${err}</li>`).join('');
        updateStrengthBar(result.score);
    }

    function getGeneratorOptions() {
        const length = parseInt(pwdLength.value) || 12; // Fallback to 12 if invalid
        const letterType = Array.from(letterTypeRadios).find(r => r.checked)?.value || 'both'; // Fallback to 'both'
        const extraType = Array.from(extraTypeRadios).find(r => r.checked)?.value || 'both'; // Fallback to 'both'

        return {
            length,
            lowercase: letterType === 'both' || letterType === 'lower',
            uppercase: letterType === 'both' || letterType === 'upper',
            numbers: extraType === 'both' || extraType === 'numbers',
            symbols: extraType === 'both' || extraType === 'symbols'
        };
    }

    // Event listeners
    showHideBtn.addEventListener('click', () => {
        const isShown = pwdInput.dataset.showPassword === 'true';
        pwdInput.dataset.showPassword = (!isShown).toString();
        pwdInput.type = isShown ? 'password' : 'text';
        showHideBtn.innerHTML = isShown ?
            (config.showHideIcon || `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-eye-slash" viewBox="0 0 16 16">
                <path d="M13.359 11.238C15.06 9.72 16 8 16 8s-3-5.5-8-5.5a7 7 0 0 0-2.79.588l.77.771A6 6 0 0 1 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755q-.247.248-.517.486z"/>
                <path d="M11.297 9.176a3.5 3.5 0 0 0-4.474-4.474l.823.823a2.5 2.5 0 0 1 2.829 2.829zm-2.943 1.299.822.822a3.5 3.5 0 0 1-4.474-4.474l.823.823a2.5 2.5 0 0 0 2.829 2.829"/>
                <path d="M3.35 5.47q-.27.24-.518.487A13 13 0 0 0 1.172 8l.195.288c.335.48.83 1.12 1.465 1.755C4.121 11.332 5.881 12.5 8 12.5c.716 0 1.39-.133 2.02-.36l.77.772A7 7 0 0 1 8 13.5C3 13.5 0 8 0 8s.939-1.721 2.641-3.238l.708.709zm10.296 8.884-12-12 .708-.708 12 12z"/>
            </svg>`) :
            (config.showIcon || `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16">
                <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/>
                <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>
            </svg>`);
    });

    createPwdBtn.addEventListener('click', () => {
        const options = getGeneratorOptions();
        const newPassword = generatePassword(options);
        pwdInput.value = newPassword;
        validatePassword();
    });

    configBtn.addEventListener('click', () => {
        configPanel.classList.toggle('active');
        configBtn.innerHTML = configPanel.classList.contains('active') ?
            (config.configUpIcon || `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-caret-up" viewBox="0 0 16 16">
                <path d="M3.204 11h9.592L8 5.519zm-.753-.659 4.796-5.48a1 1 0 0 1 1.506 0l4.796 5.48c.566.647.106 1.659-.753 1.659H3.204a1 0 0 1-.753-1.659"/>
            </svg>`) :
            (config.configIcon || `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-caret-down" viewBox="0 0 16 16">
                <path d="M3.204 5h9.592L8 10.481zm-.753.659 4.796 5.48a1 1 0 0 0 1.506 0l4.796-5.48c.566-.647.106-1.659-.753-1.659H3.204a1 0 0 0-.753 1.659"/>
            </svg>`);
    });

    pwdInput.addEventListener('input', validatePassword);

    // Initial validation
    validatePassword();
}

// Automatically initialize all password components on the page
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-password-component]').forEach(elem => {
        const instanceId = elem.id;
        const config = {
            showHideIcon: elem.dataset.showHideIcon || '',
            showIcon: elem.dataset.showIcon || '',
            configIcon: elem.dataset.configIcon || '',
            configUpIcon: elem.dataset.configUpIcon || ''
        };
        initPasswordComponent(instanceId, config);
    });
});