// E-Governance Authentication Module
// Handles user authentication, validation, and session management

/**
 * Validate email format
 * @param {string} email - Email address to validate
 * @returns {boolean} - True if email is valid
 */
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

/**
 * Validate password strength
 * Requires: uppercase, lowercase, number, and special character
 * @param {string} password - Password to validate
 * @returns {boolean} - True if password meets criteria
 */
function isValidPassword(password) {
    const hasUppercase = /[A-Z]/.test(password);
    const hasLowercase = /[a-z]/.test(password);
    const hasNumber = /[0-9]/.test(password);
    const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password);
    
    return hasUppercase && hasLowercase && hasNumber && hasSpecial;
}

/**
 * Toggle password visibility
 * @param {string} fieldId - ID of the password input field
 */
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    if (field.type === 'password') {
        field.type = 'text';
    } else {
        field.type = 'password';
    }
}

/**
 * Hash password (simple client-side representation)
 * Note: In production, use bcrypt or similar on server
 * @param {string} password - Password to hash
 * @returns {string} - Hashed password
 */
function hashPassword(password) {
    // Simple hash for demo purposes
    let hash = 0;
    for (let i = 0; i < password.length; i++) {
        const char = password.charCodeAt(i);
        hash = ((hash << 5) - hash) + char;
        hash = hash & hash; // Convert to 32-bit integer
    }
    return 'hash_' + Math.abs(hash);
}

/**
 * Store user session in localStorage
 * @param {object} user - User object
 */
function storeUserSession(user) {
    sessionStorage.setItem('currentUser', JSON.stringify(user));
    sessionStorage.setItem('sessionTime', new Date().getTime());
}

/**
 * Retrieve user session from localStorage
 * @returns {object|null} - User object or null if not found
 */
function getUserSession() {
    const userJson = sessionStorage.getItem('currentUser');
    return userJson ? JSON.parse(userJson) : null;
}

/**
 * Clear user session
 */
function clearUserSession() {
    sessionStorage.removeItem('currentUser');
    sessionStorage.removeItem('sessionTime');
}

/**
 * Check if user is logged in
 * @returns {boolean} - True if user is logged in
 */
function isUserLoggedIn() {
    return getUserSession() !== null;
}

/**
 * Redirect to login if not authenticated
 */
function requireLogin() {
    if (!isUserLoggedIn()) {
        window.location.href = 'index.html';
    }
}

/**
 * Redirect to dashboard if already logged in
 */
function redirectIfLoggedIn() {
    if (isUserLoggedIn()) {
        const user = getUserSession();
        if (user.role === 'Admin') {
            window.location.href = 'admin-panel.html';
        } else {
            window.location.href = 'dashboard.html';
        }
    }
}

/**
 * Login user with email and password
 * @param {string} email - User email
 * @param {string} password - User password
 * @returns {Promise} - Promise that resolves with login result
 */
async function loginUser(email, password) {
    try {
        const response = await fetch('auth_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'login',
                email: email,
                password: password
            })
        });

        const result = await response.json();
        return result;
    } catch (error) {
        console.error('Login error:', error);
        return { success: false, message: `Network error: ${error.message}` };
    }
}

/**
 * Register new user
 * @param {object} userData - User registration data
 * @returns {Promise} - Promise that resolves with registration result
 */
async function registerUser(userData) {
    try {
        console.log('registerUser called with data:', userData);
        const response = await fetch('auth_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'register',
                firstName: userData.firstName,
                lastName: userData.lastName,
                email: userData.email,
                password: userData.password,
                phone: userData.phone,
                nationalId: userData.nationalId,
                district: userData.district,
                province: userData.province
            })
        });

        console.log('registerUser fetch response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        const result = await response.json();
        console.log('registerUser API result:', result);
        return result;
    } catch (error) {
        console.error('Registration error:', error);
        return { success: false, message: `Network error: ${error.message}` };
    }
}

/**
 * Check if user exists
 * @param {string} email - User email
 * @returns {Promise} - Promise that resolves with existence check result
 */
async function checkUserExists(email) {
    try {
        const response = await fetch('auth_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'check_user',
                email: email
            })
        });

        const result = await response.json();
        return result;
    } catch (error) {
        console.error('Check user error:', error);
        return { exists: false };
    }
}

/**
 * Generate unique user ID
 * @returns {string} - Unique user ID
 */
function generateUserId() {
    return 'USER_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
}

/**
 * Send password recovery email
 * @param {string} email - User email
 * @returns {boolean} - True if email sent
 */
function sendPasswordRecoveryEmail(email) {
    try {
        // In real app, send actual email via backend
        const recoveryToken = 'recovery_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        localStorage.setItem('recoveryToken_' + email, recoveryToken);
        
        console.log('Password recovery email sent to:', email);
        console.log('Recovery token:', recoveryToken);
        return true;
    } catch (error) {
        console.error('Recovery email error:', error);
        return false;
    }
}

/**
 * Reset user password
 * @param {string} email - User email
 * @param {string} newPassword - New password
 * @returns {boolean} - True if password reset successful
 */
function resetUserPassword(email, newPassword) {
    try {
        const users = JSON.parse(localStorage.getItem('users') || '[]');
        const user = users.find(u => u.email === email);

        if (user) {
            user.passwordHash = hashPassword(newPassword);
            localStorage.setItem('users', JSON.stringify(users));
            console.log('Password reset successful');
            return true;
        }

        return false;
    } catch (error) {
        console.error('Password reset error:', error);
        return false;
    }
}

/**
 * Update user profile
 * @param {string} userId - User ID
 * @param {object} updates - Fields to update
 * @returns {boolean} - True if update successful
 */
function updateUserProfile(userId, updates) {
    try {
        const users = JSON.parse(localStorage.getItem('users') || '[]');
        const user = users.find(u => u.id === userId);

        if (user) {
            Object.assign(user, updates);
            localStorage.setItem('users', JSON.stringify(users));
            console.log('Profile updated successfully');
            return true;
        }

        return false;
    } catch (error) {
        console.error('Profile update error:', error);
        return false;
    }
}

/**
 * Logout user
 */
function logoutUser() {
    clearUserSession();
    console.log('User logged out');
}

/**
 * Check if email already exists
 * @param {string} email - Email to check
 * @returns {boolean} - True if email exists
 */
function emailExists(email) {
    const users = JSON.parse(localStorage.getItem('users') || '[]');
    return users.some(u => u.email === email);
}

// Initialize demo users if not exist
function initializeDemoUsers() {
    const existingUsers = localStorage.getItem('users');
    
    if (!existingUsers) {
        const demoUsers = [
            {
                id: 'USER_DEMO_001',
                firstName: 'John',
                lastName: 'Doe',
                fullName: 'John Doe',
                email: 'citizen@example.com',
                nrc: '111222333444',
                phone: '+260123456789',
                passwordHash: hashPassword('password123'),
                role: 'Citizen',
                district: 'Lusaka',
                province: 'Lusaka',
                createdAt: new Date().toISOString(),
                verified: true
            },
            {
                id: 'USER_DEMO_002',
                firstName: 'Admin',
                lastName: 'Officer',
                fullName: 'Admin Officer',
                email: 'admin@council.gov',
                nrc: '555666777888',
                phone: '+260987654321',
                passwordHash: hashPassword('admin123'),
                role: 'Admin',
                district: 'Lusaka',
                province: 'Lusaka',
                createdAt: new Date().toISOString(),
                verified: true
            }
        ];
        
        localStorage.setItem('users', JSON.stringify(demoUsers));
        console.log('Demo users initialized');
    }
}

// Initialize demo users on script load
document.addEventListener('DOMContentLoaded', function() {
    initializeDemoUsers();
});

// Export functions for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        isValidEmail,
        isValidPassword,
        togglePassword,
        hashPassword,
        storeUserSession,
        getUserSession,
        clearUserSession,
        isUserLoggedIn,
        requireLogin,
        redirectIfLoggedIn,
        registerUser,
        loginUser,
        generateUserId,
        sendPasswordRecoveryEmail,
        resetUserPassword,
        updateUserProfile,
        logoutUser,
        emailExists,
        initializeDemoUsers
    };
}
