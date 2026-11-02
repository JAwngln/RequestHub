// Auto-logout functionality
console.log("Auto-logout script loaded");

// Set auto-logout timer (30 minutes)
const AUTO_LOGOUT_TIME = 30 * 60 * 1000; // 30 minutes in milliseconds

let logoutTimer;

function resetLogoutTimer() {
    clearTimeout(logoutTimer);
    logoutTimer = setTimeout(() => {
        console.log("Auto-logging out due to inactivity");
        window.location.href = 'login.php';
    }, AUTO_LOGOUT_TIME);
}

// Reset timer on user activity
document.addEventListener('mousemove', resetLogoutTimer);
document.addEventListener('keypress', resetLogoutTimer);
document.addEventListener('click', resetLogoutTimer);
document.addEventListener('scroll', resetLogoutTimer);

// Start timer when page loads
resetLogoutTimer();
