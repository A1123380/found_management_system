# 校園失物招領管理系統

一個功能完整的校園失物招領管理系統，提供失物登記、認領申請、管理員審核等功能。

## 功能特色

### 用戶管理
- **多種登入方式**：支援用戶名、學號、工號、手機號碼或電子郵件登入
- **電子郵件驗證**：註冊後需通過電子郵件驗證
- **管理員審核**：新用戶註冊後需等待管理員審核
- **密碼重設**：忘記密碼時可透過電子郵件重設
- **個人資料管理**：支援個人資料編輯和密碼變更

### 失物管理
- **失物登記**：支援遺失物品和拾獲物品的登記
- **圖片上傳**：可上傳失物圖片，無圖片時顯示預設圖片
- **地點標記**：整合Google Maps API，支援地圖定位
- **狀態追蹤**：失物狀態包括待審核、已通過、已認領、已歸還等

### 搜尋與篩選
- **多條件搜尋**：支援類型、狀態、關鍵字等篩選條件
- **分頁顯示**：支援自定義每頁顯示數量
- **排序功能**：支援多欄位排序
- **即時搜尋**：AJAX技術實現無刷新搜尋

### 通知系統
- **系統公告**：管理員可發布公開或管理員專用公告
- **個人通知**：登入異常、申請狀態變更等個人通知
- **電子郵件通知**：重要事件自動發送電子郵件

### 安全功能
- **Session管理**：防止多裝置同時登入
- **權限控制**：管理員和一般用戶權限分離
- **輸入驗證**：防止SQL注入和XSS攻擊
- **Cloudflare Turnstile**：防機器人驗證（可選）

## 技術架構

- **後端**：PHP 8.2+
- **資料庫**：MySQL 10.4+
- **前端**：HTML5, CSS3, JavaScript (ES6+)
- **郵件服務**：PHPMailer
- **地圖服務**：Google Maps API
- **防機器人**：Cloudflare Turnstile

## 系統需求

- PHP 8.2 或更高版本
- MySQL 10.4 或更高版本
- 支援SMTP的郵件服務
- Google Maps API Key（可選）
- Cloudflare Turnstile（可選）

## 安裝說明

### 1. 環境準備
```bash
# 確保PHP和MySQL已安裝
php -v
mysql --version
```

### 2. 資料庫設定
```sql
# 創建資料庫
CREATE DATABASE lost_and_found CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# 匯入資料庫結構
mysql -u root -p lost_and_found < lost_and_found.sql
```

### 3. 環境配置
在 `files/` 目錄下創建 `.env` 文件：
```env
# 資料庫設定
DB_HOST=localhost
DB_NAME=lost_and_found
DB_USER=your_username
DB_PASS=your_password

# 郵件服務設定
SMTP_USERNAME=your_email@example.com
SMTP_PASSWORD=your_email_password

# 應用程式設定
BASE_URL=http://your-domain.com/
TIMEZONE=Asia/Taipei
```

### 4. 郵件服務配置
在以下文件中設定SMTP帳號密碼：
- `user_profile.php`
- `register.php`
- `forgot_password.php`

### 5. API Key 配置（可選）
- **Google Maps API**：在 `add_item.php` 和 `edit_item.php` 中設定
- **Cloudflare Turnstile**：在 `cf-turnstile.inc` 中設定

### 6. 檔案權限
```bash
# 確保uploads目錄可寫入
chmod 755 files/uploads/
```

## 使用說明

### 管理員功能
1. **用戶管理**：審核新用戶註冊、管理現有用戶
2. **失物審核**：審核失物登記、處理認領申請
3. **系統公告**：發布和管理系統公告
4. **數據統計**：查看系統使用統計

### 一般用戶功能
1. **失物登記**：登記遺失或拾獲的物品
2. **失物搜尋**：搜尋和篩選失物資訊
3. **認領申請**：申請認領其他用戶的失物
4. **個人管理**：管理個人資料和失物記錄

## 開發說明

### 詳細目錄結構
```
├── README.md                    # 專案說明文件
├── .gitignore                  # Git忽略文件
├── config.example.php          # 配置範例文件
├── readme.txt                  # 原始說明文件
├── lost_and_found.sql          # 資料庫結構文件
└── files/                      # 主要程式碼目錄
    ├── assest/                 # 圖片資源
    │   ├── favicon-32x32.ico  # 網站圖示
    │   ├── favicon-128x128.ico
    │   ├── favicon-256x256.ico
    │   ├── favicon.ico
    │   └── placeholder.jpg    # 預設失物圖片
    ├── css/                    # 樣式表文件
    │   ├── styles.css         # 主要樣式表
    │   ├── login.css          # 登入頁面樣式
    │   └── forgot_password.css # 密碼重設頁面樣式
    ├── js/                     # JavaScript文件
    ├── uploads/                # 用戶上傳文件目錄
    │   └── .gitkeep           # 保持目錄結構
    ├── PHPMailer/              # 郵件服務庫
    ├── config.php              # 資料庫和環境配置
    ├── index.php               # 登入頁面
    ├── register.php            # 用戶註冊頁面
    ├── user_profile.php        # 用戶個人資料頁面
    ├── admin_profile.php       # 管理員個人資料頁面
    ├── user_dashboard.php      # 用戶儀表板
    ├── admin_dashboard.php     # 管理員儀表板
    ├── add_item.php            # 新增失物頁面
    ├── edit_item.php           # 編輯失物頁面
    ├── claim_item.php          # 認領失物頁面
    ├── approve_item.php        # 審核失物
    ├── reject_item.php         # 拒絕失物
    ├── approve_claim.php       # 審核認領申請
    ├── reject_claim.php        # 拒絕認領申請
    ├── approve_user.php        # 審核用戶註冊
    ├── reject_user.php         # 拒絕用戶註冊
    ├── delete_item.php         # 刪除失物（用戶）
    ├── delete_item_admin.php   # 刪除失物（管理員）
    ├── delete_user.php         # 刪除用戶
    ├── admin_post_announcement.php # 發布公告
    ├── delete_announcement.php # 刪除公告
    ├── forgot_password.php     # 忘記密碼頁面
    ├── reset_password.php      # 重設密碼頁面
    ├── verify_email.php        # 電子郵件驗證
    ├── logout.php              # 登出功能
    ├── loading.html            # 載入頁面
    ├── functions.php           # 通用功能函數
    ├── admin_functions.php     # 管理員專用功能
    ├── cf-turnstile.inc        # Cloudflare Turnstile配置
    ├── get_items.php           # 獲取失物列表API
    └── get_all_items.php       # 獲取所有失物API
```

### 主要文件說明
- `index.php` - 登入頁面，支援多種登入方式
- `admin_dashboard.php` - 管理員儀表板，包含用戶管理、失物審核等功能
- `user_dashboard.php` - 用戶儀表板，包含失物地圖、個人失物等功能
- `functions.php` - 通用功能函數，包含搜尋、分頁、排序等
- `admin_functions.php` - 管理員專用功能，包含審核、統計等
- `config.php` - 資料庫連線和環境配置
- `add_item.php` - 新增失物，支援圖片上傳和地圖定位
- `claim_item.php` - 認領失物申請流程

## 測試流程

### 基本失物測試流程
1. **註冊帳戶**：於email驗證該用戶並認證註冊
2. **管理員審核**：用戶註冊成功後，需經過admin審核確認
3. **用戶登入**：admin審核完成，該用戶即可登入
4. **失物上傳**：用戶可上傳"遺失失物"、"拾獲失物"兩者
5. **失物地圖**：用戶可申請其他user的失物，但無法申請自己的失物
6. **申請審核**：申請失物需等待admin審核，若遭拒絕得再24小時後才可再次申請該失物
7. **申請成功**：申請成功的失物會在失物紀錄中顯示
8. **資料下載**：用戶可在個人資訊處下載失物資料.csv
9. **登出功能**："登出"位置在個人資訊欄
10. **圖片預覽**：於失物標題懸浮游標時，可檢視該失物圖片
11. **資料變更**：user個人資訊，變更資料需要email驗證

## 注意事項

- 部署前請務必修改所有預設密碼
- 定期備份資料庫
- 確保伺服器安全性設定
- 遵守相關隱私保護法規

## 授權條款

本專案僅供學習和研究使用。
