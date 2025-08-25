document.addEventListener('DOMContentLoaded', () => {
    const tabButtons = document.querySelectorAll('.notification-tab-button');
    const contents = document.querySelectorAll('.notification-content');
    const bell = document.querySelector('.notification-bell');
    const dropdown = document.getElementById('notificationDropdown');
    const markAllReadButton = document.getElementById('mark-all-read-button');
    const notificationList = document.getElementById('notification-list');
    const announcementList = document.getElementById('announcement-list');
    const badge = document.querySelector('.badge');
    const markAllReadForm = document.getElementById('mark-all-read-form');

    const currentPage = window.location.pathname.includes('admin_dashboard') ? 'admin_dashboard.php' : 'user_dashboard.php';

    if (dropdown) {
        dropdown.style.display = 'none';
    }

    function updateMarkAllReadButton() {
        const activeTab = document.querySelector('.notification-tab-button.active').getAttribute('data-tab');
        let hasUnreadItems = false;

        if (activeTab === 'notifications') {
            const unreadNotifications = notificationList?.querySelectorAll('li.unread');
            hasUnreadItems = unreadNotifications && unreadNotifications.length > 0;
            if (markAllReadButton) {
                markAllReadButton.textContent = '全部個人通知標記為已讀';
            }
        } else if (activeTab === 'announcements') {
            const unreadAnnouncements = announcementList?.querySelectorAll('li.announcement:not(.read)');
            hasUnreadItems = unreadAnnouncements && unreadAnnouncements.length > 0;
            if (markAllReadButton) {
                markAllReadButton.textContent = '全部公告標記為已讀';
            }
        }

        if (markAllReadForm && markAllReadButton) {
            markAllReadForm.style.display = 'block'; 
            markAllReadButton.disabled = !hasUnreadItems; 
        }
    }

    tabButtons.forEach(button => {
        button.addEventListener('click', (e) => {
            e.stopPropagation();
            tabButtons.forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');
            contents.forEach(content => content.style.display = 'none');
            const tabId = button.getAttribute('data-tab');
            document.getElementById(tabId).style.display = 'block';

            updateMarkAllReadButton();
        });
    });

    bell.addEventListener('click', (e) => {
        e.stopPropagation();
        const isDropdownVisible = dropdown.style.display === 'block';
        dropdown.style.display = isDropdownVisible ? 'none' : 'block';
        
        if (!isDropdownVisible) {
            updateMarkAllReadButton();
        }
    });

    document.addEventListener('click', (e) => {
        if (!bell.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });

    dropdown.addEventListener('click', (e) => {
        e.stopPropagation();
    });

    if (markAllReadButton) {
        markAllReadButton.addEventListener('click', async (e) => {
            e.preventDefault();
            e.stopPropagation();

            const activeTab = document.querySelector('.notification-tab-button.active').getAttribute('data-tab');
            let action = '';
            let list = null;

            if (activeTab === 'notifications') {
                action = 'mark_all_notifications_read';
                list = notificationList;
            } else if (activeTab === 'announcements') {
                action = 'mark_all_announcements_read';
                list = announcementList;
            }

            if (!action || !list) return;

            markAllReadButton.disabled = true;
            const originalText = markAllReadButton.textContent;
            markAllReadButton.textContent = '處理中...';

            try {
                const response = await fetch(`${currentPage}?action=${action}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                });
                const result = await response.json();

                if (result.success) {
                    const unreadItems = list.querySelectorAll('li.unread, li.announcement:not(.read)');
                    unreadItems.forEach(item => {
                        item.classList.remove('unread');
                        item.classList.add('read');
                    });

                    if (badge) {
                        badge.style.display = result.unread_count > 0 ? 'block' : 'none';
                        badge.textContent = result.unread_count;
                    }

                    updateMarkAllReadButton();
                } else {
                    console.error('標記失敗:', result.message);
                }
            } catch (error) {
                console.error('AJAX 請求失敗:', error);
            } finally {
                markAllReadButton.disabled = false;
                markAllReadButton.textContent = originalText;
            }
        });
    }

    updateMarkAllReadButton();
});