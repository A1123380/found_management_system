let map;
let markers = [];
let openInfoWindow = null; 

function initMap() {
    map = new google.maps.Map(document.getElementById('map'), {
        center: { lat: 22.6273, lng: 120.3014 },
        zoom: 12,
        mapTypeControl: false,
        streetViewControl: false
    });

    markers.forEach(marker => marker.setMap(null));
    markers = [];

    const showClaimed = document.getElementById('showClaimed').checked;
    const showPendingClaims = document.getElementById('showPendingClaims').checked;
    const showFound = document.getElementById('showFound').checked;
    const showLost = document.getElementById('showLost').checked;

    console.log('篩選狀態:', { showClaimed, showPendingClaims, showFound, showLost });

    const isAdmin = window.location.pathname.includes('admin_dashboard.php');
    const apiUrl = isAdmin ? 'get_all_items.php' : 'get_items.php';

    const statusDiv = document.getElementById('map-status');
    statusDiv.textContent = '正在載入失物資料...';

    fetch(apiUrl)
        .then(response => {
            if (!response.ok) {
                throw new Error('網路錯誤: ' + response.status);
            }
            return response.json();
        })
        .then(items => {
            if (items.error) {
                throw new Error(items.error);
            }

            console.log('接收到的項目數量:', items.length);

            let visibleItems = 0;

            items.forEach(item => {
                console.log('項目資料:', item);

                if (item.status === 'claimed') {
                    console.log('找到 claimed 項目:', item);
                }
                if (item.has_pending_claim) {
                    console.log('找到 has_pending_claim 項目:', item);
                }
                if (item.item_type === 'lost_by_user' && item.status !== 'claimed' && !item.has_pending_claim && item.approval_status === 'approved') {
                    console.log('找到 lost_by_user 項目:', item);
                }
                if (item.item_type === 'found_by_user' && item.status !== 'claimed' && !item.has_pending_claim && item.approval_status === 'approved') {
                    console.log('找到 found_by_user 項目:', item);
                }

                let shouldShow = false;
                if (showClaimed && item.status === 'claimed') {
                    shouldShow = true;
                } else if (showPendingClaims && item.has_pending_claim) {
                    shouldShow = true;
                } else if (showFound && item.item_type === 'found_by_user' && item.status !== 'claimed' && !item.has_pending_claim && item.approval_status === 'approved') {
                    shouldShow = true;
                } else if (showLost && item.item_type === 'lost_by_user' && item.status !== 'claimed' && !item.has_pending_claim && item.approval_status === 'approved') {
                    shouldShow = true;
                }

                if (!shouldShow) {
                    return;
                }

                visibleItems++;  

                if (!item.location || typeof item.location !== 'string') {
                    console.warn(`無效的地點資料: item.id=${item.id}, location=${item.location}`);
                    return;
                }

                const coords = item.location.split(',').map(coord => parseFloat(coord.trim()));
                const [lat, lng] = coords;

                if (coords.length !== 2 || isNaN(lat) || isNaN(lng) || lat < -90 || lat > 90 || lng < -180 || lng > 180) {
                    console.warn(`無效的座標: item.id=${item.id}, location=${item.location}, parsed=${coords}`);
                    return;
                }

                let iconUrl;
                if (item.status === 'claimed') {
                    iconUrl = 'http://maps.google.com/mapfiles/ms/icons/green-dot.png';
                } else if (item.has_pending_claim) {
                    iconUrl = 'http://maps.google.com/mapfiles/ms/icons/yellow-dot.png';
                } else if (item.item_type === 'lost_by_user') {
                    iconUrl = 'http://maps.google.com/mapfiles/ms/icons/red-dot.png';
                } else if (item.item_type === 'found_by_user') {
                    iconUrl = 'http://maps.google.com/mapfiles/ms/icons/blue-dot.png';
                }

                const marker = new google.maps.Marker({
                    position: { lat, lng },
                    map: map,
                    title: item.title,
                    icon: {
                        url: iconUrl,
                        scaledSize: new google.maps.Size(32, 32)
                    }
                });

                let infoContent = `<div class="info-window"><h3>${item.title}</h3>`;
                if (item.status === 'claimed') {
                    infoContent += `<p>狀態: ${item.item_type === 'lost_by_user' ? '已找回' : '已歸還'}</p>`;
                } else if (item.has_pending_claim) {
                    infoContent += '<p>狀態: 已有人申請待審核</p>';
                } else if (item.user_id == currentUserId) {
                    infoContent += '<p>狀態: 不可申請（自己的失物）</p>';
                } else if (item.approval_status !== 'approved') {
                    infoContent += '<p>狀態: 不可申請（未通過審核）</p>';
                } else {
                    infoContent += `<button onclick="window.location.href='claim_item.php?item_id=${item.id}&tab=home'">申請提繳</button>`;
                }
                infoContent += '</div>';

                const infoWindow = new google.maps.InfoWindow({
                    content: infoContent
                });

                marker.addListener('click', () => {
                    if (openInfoWindow) {
                        openInfoWindow.close();
                    }

                    infoWindow.open(map, marker);
                    openInfoWindow = infoWindow; 
                });
                
                markers.push(marker);
            });

            statusDiv.textContent = `已顯示 ${visibleItems} 個失物`;
            if (visibleItems === 0) {
                statusDiv.textContent = '無符合條件的失物';
            }
        })
        .catch(error => {
            console.error('載入失物失敗:', error);
            statusDiv.textContent = '載入失物失敗: ' + error.message;
            document.getElementById('map-error').style.display = 'block';
        });
}
