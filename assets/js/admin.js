//CyberWeb Admin JavaScript
//Handles admin panel interactive features

//Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
});

//Confirm delete actions
function confirmDelete(message) {
    return confirm(message || 'Are you sure you want to delete this item? This action cannot be undone.');
}

//Block user confirmation
function confirmBlock(username) {
    return confirm(`Are you sure you want to block user "${username}"?`);
}

//Unblock user confirmation
function confirmUnblock(username) {
    return confirm(`Are you sure you want to unblock user "${username}"?`);
}

//Delete user confirmation
function confirmDeleteUser(username) {
    return confirm(`Are you sure you want to permanently delete user "${username}"? This will delete all their posts, comments, and data. This action cannot be undone.`);
}

//Handle report action
async function handleReport(reportId, action) {
    if (!confirm(`Are you sure you want to ${action} this report?`)) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('report_id', reportId);
        formData.append('csrf_token', csrfToken);

        const response = await fetch('reports.php', {
            method: 'POST',
            body: formData
        });

        if (response.ok) {
            location.reload();
        } else {
            alert('Failed to process report. Please try again.');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    }
}

//Filter tables
function filterTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    const filter = input.value.toUpperCase();
    const table = document.getElementById(tableId);
    const tr = table.getElementsByTagName('tr');

    for (let i = 1; i < tr.length; i++) {
        let txtValue = tr[i].textContent || tr[i].innerText;
        if (txtValue.toUpperCase().indexOf(filter) > -1) {
            tr[i].style.display = '';
        } else {
            tr[i].style.display = 'none';
        }
    }
}

//Sort table
function sortTable(tableId, columnIndex) {
    const table = document.getElementById(tableId);
    let switching = true;
    let dir = 'asc';
    let switchcount = 0;

    while (switching) {
        switching = false;
        const rows = table.rows;

        for (let i = 1; i < (rows.length - 1); i++) {
            let shouldSwitch = false;
            const x = rows[i].getElementsByTagName('TD')[columnIndex];
            const y = rows[i + 1].getElementsByTagName('TD')[columnIndex];

            if (dir === 'asc') {
                if (x.innerHTML.toLowerCase() > y.innerHTML.toLowerCase()) {
                    shouldSwitch = true;
                    break;
                }
            } else if (dir === 'desc') {
                if (x.innerHTML.toLowerCase() < y.innerHTML.toLowerCase()) {
                    shouldSwitch = true;
                    break;
                }
            }
        }

        if (shouldSwitch) {
            rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
            switching = true;
            switchcount++;
        } else {
            if (switchcount === 0 && dir === 'asc') {
                dir = 'desc';
                switching = true;
            }
        }
    }
}
