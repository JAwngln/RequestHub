function toggleNotif() {
    const notif = document.getElementById("notifBox");
    if (!notif) return;
    const isHidden = notif.style.display === "none" || notif.style.display === "";
    notif.style.display = isHidden ? "flex" : "none";
    if (isHidden && typeof loadNotifications === "function") {
        loadNotifications();
    }
}

// Keep the sidebar in sync with the page currently shown in the content area
function setActiveNav(page) {
    const path = String(page || '').split('?')[0];
    document.querySelectorAll('.sidebar-nav .nav-link[data-page]').forEach(link => {
        const isActive = link.dataset.page === path;
        link.classList.toggle('active', isActive);
        if (isActive) {
            link.setAttribute('aria-current', 'page');
        } else {
            link.removeAttribute('aria-current');
        }
    });
}

function loadPage(page) {
    setActiveNav(page);
    if (window.__notificationTargetClearTimer) {
        window.clearTimeout(window.__notificationTargetClearTimer);
        window.__notificationTargetClearTimer = null;
    }
    const targetParams = new URLSearchParams(String(page).split('?')[1] || '');
    window.notificationTargetId = targetParams.get('request_id') || '';
    window.notificationTargetAlignedId = '';
    // Bypass any cached copy of the tab HTML so updated form controls (such as
    // the date-only vehicle picker) are always what the user sees.
    const fetchUrl = page + (page.includes('?') ? '&' : '?') + '_ts=' + Date.now();
    fetch(fetchUrl, { cache: 'no-store' })
        .then(res => res.text())
        .then(html => {
            document.getElementById("mainContent").innerHTML = html;
            
            // Execute scripts if loading vehicle.php, request.php, or calendar.php
            if (page.includes('vehicle.php') || page.includes('request.php') || page.includes('calendar.php')) {
                // Wait a bit for DOM to be ready
                setTimeout(() => {
                    // Remove any existing vehicle.js script to prevent duplicates
                    const existingScript = document.querySelector('script[src*="vehicle.js"]');
                    if (existingScript) {
                        existingScript.remove();
                    }
                    
                    // Execute ALL inline scripts from the loaded content
                    const scripts = document.getElementById("mainContent").querySelectorAll('script');
                    scripts.forEach(script => {
                        if (!script.src) { // Only execute inline scripts
                            const newScript = document.createElement('script');
                            newScript.textContent = script.textContent;
                            document.body.appendChild(newScript); // Append to body instead of mainContent
                        }
                    });
                    
                    // Load vehicle.js only for vehicle.php
                    if (page.includes('vehicle.php')) {
                        const script = document.createElement('script');
                        script.src = 'pages/vehicle.js?v=9';
                        script.onload = function() {
                            // Initialize the vehicle form if function exists
                            setTimeout(() => {
                                if (typeof initVehicleForm === 'function') {
                                    initVehicleForm();
                                }
                                // Check if our submit function is available
                                if (typeof submitVehicleRequest === 'function') {
                                    // Vehicle request function available
                                } else {
                                    // Vehicle request function not found, but inline should work
                                }
                                
                                // Initialize displayRequests if it exists (vehicle requests only)
                                if (typeof displayRequests === 'function') {
                                    displayRequests();
                                }
                                
                                // Do NOT load requested items for vehicle page - only show vehicle requests
                            }, 200);
                        };
                        script.onerror = function() {
                            // Failed to load vehicle.js, but inline functions should work
                            // Try to initialize displayRequests anyway (vehicle requests only)
                            if (typeof displayRequests === 'function') {
                                displayRequests();
                            }
                            // Do NOT load requested items for vehicle page
                        };
                        document.body.appendChild(script);
                    }
                    
                    // Load request.js only for request.php
                    if (page.includes('request.php')) {
                        const script = document.createElement('script');
                        script.src = 'pages/request.js';
                        script.onload = function() {
                            // Initialize request form if function exists
                            setTimeout(() => {
                                if (typeof initRequestForm === 'function') {
                                    initRequestForm();
                                }
                                // Check if our submit function is available
                                if (typeof submitRequest === 'function') {
                                    // Request function available
                                } else {
                                    // Request function not found, but inline should work
                                }
                                // Load inventory items for dropdown
                                if (typeof loadInventoryItems === 'function') {
                                    loadInventoryItems();
                                }
                                // Load requested items list
                                if (typeof loadRequestedItems === 'function') {
                                    loadRequestedItems();
                                }
                            }, 200);
                        };
                        document.body.appendChild(script);
                    }
                    
                    // Initialize display functions for vehicle and request pages only
                    setTimeout(() => {
                        if (typeof displayRequests === 'function') {
                            displayRequests();
                        }
                        if (typeof displayItemRequests === 'function') {
                            displayItemRequests();
                        }
                        // Always try to load requested items for consistency
                        if (typeof loadRequestedItems === 'function') {
                            loadRequestedItems();
                        }
                    }, 200);
                }, 100);
            }
        })
        .catch(() => {
            document.getElementById("mainContent").innerHTML =
                "<h2 style='text-align:center'>Page not found</h2>";
        });
}

// ✅ AUTO LOAD WELCOME PAGE (handled in home.php)
// window.onload = () => {
//     loadPage("pages/vehicle.php");
// };
let rowCount = 1;

function addRow() {
    rowCount++;
    const tbody = document.getElementById("itemBody");

    const row = document.createElement("tr");
    row.innerHTML = `
        <td>#${String(rowCount).padStart(3, '0')}</td>
        <td>
            <select id="itemSelect">
                <option disabled selected>SELECT ITEM</option>
            </select>
        </td>
        <td><input type="number" min="1"></td>
        <td><input type="text"></td>
        <td>
            <button class="delete-btn" onclick="deleteRow(this)">✖</button>
        </td>
    `;
    tbody.appendChild(row);
    
    // Load inventory items for the new dropdown
    setTimeout(() => {
        if (typeof loadInventoryItems === 'function') {
            loadInventoryItems();
        }
    }, 100);
}

function deleteRow(btn) {
    btn.closest("tr").remove();
    updateNumbers();
}

function updateNumbers() {
    document.querySelectorAll("#itemBody tr").forEach((row, i) => {
        row.children[0].textContent = "#" + String(i + 1).padStart(3, '0');
    });
    rowCount = document.querySelectorAll("#itemBody tr").length;
}
function editProfile() {
    alert("Edit profile clicked!");
    // later: open modal or load profile page
}
