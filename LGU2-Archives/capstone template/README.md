# LLRM System - Capstone Template

## Overview
This is a **fully functional standalone version** of the Legislative Records Management (LLRM) System built with pure **HTML, CSS, and JavaScript**. It replicates all the core features of the PHP-based LLRM System without requiring a backend server or database.

## Features

### 🎨 Modern UI/UX Design
- **Red Theme**: Matching Valenzuela City branding (#dc2626, #b91c1c)
- **Smooth Animations**: Fade-in, slide-in, bounce effects with staggered entrance
- **Responsive Design**: Mobile-friendly with collapsible sidebar
- **Interactive Elements**: Hover effects, transforms, and transitions
- **Tailwind CSS**: Utility-first CSS framework for rapid development

### 📊 Dashboard Module
- **Statistics Cards**: Total Documents, Approved, Pending, Active Users
- **Interactive Charts**: Chart.js integration for data visualization
  - Document Types (Doughnut Chart)
  - Documents Over Time (Line Chart)
  - Documents by Status (Bar Chart)
- **Quick Actions Panel**: Fast access to common tasks
- **Recent Documents Table**: Latest uploaded documents with status badges

### 📁 Document Management
- **Full CRUD Operations**: Create, Read, Update, Delete documents
- **Advanced Filtering**:
  - Filter by document type (Ordinance, Resolution, Session, Agenda)
  - Filter by status (Approved, Pending, Draft)
  - Real-time search across title, reference, and description
- **Sortable Table**: Click column headers to sort
- **Document Details Modal**: View complete document information including:
  - Reference number, title, type, status
  - Upload date, uploaded by, file size
  - Views and downloads count
  - Tags and description
- **Status Badges**: Color-coded status indicators

### 🔍 Advanced Search
- **Multi-Field Search**:
  - Keywords (searches title, description, tags)
  - Reference number
  - Document type
  - Status
  - Date range (from/to)
- **Results Display**: Card-based layout with full document details
- **Result Count**: Shows number of matching documents

### 📈 Reports & Analytics
- **Metric Cards**: Monthly uploads, total views, total downloads with trend indicators
- **Multiple Chart Types**:
  - Documents over time (Line Chart)
  - Documents by status (Bar Chart)
- **Top Uploaders**: Leaderboard of most active users
- **Popular Documents**: Most viewed documents list

### 👥 User Management
- **User Listing**: Complete user directory with avatars
- **User Information**: Name, email, role, department, status, last login
- **User Actions**:
  - Edit user details
  - Toggle user status (Active/Inactive)
  - Delete users
- **Role Management**: Administrator, Officer, Staff, Viewer
- **Avatar Display**: Color-coded initials for users

### 🛡️ Audit Logs
- **Activity Tracking**: Complete system activity history
- **Filterable Logs**:
  - Filter by action type (Upload, Approve, Update, Delete)
  - Filter by user
  - Filter by date
- **Detailed Information**: User, action, description, timestamp, IP address
- **Action Badges**: Color-coded action indicators
- **Automatic Logging**: All user actions are automatically logged

### 👤 Enhanced Profile Page
Matching the LLRM System design with:

#### Profile Header
- **Large Red Gradient Banner**: Matching system design
- **Profile Picture Display**: 
  - Large circular avatar (132x132px)
  - White border with shadow
  - Fallback to initials if no image
- **Functional Upload Button**: 
  - Camera icon overlay
  - Click to upload new profile picture
  - Image preview before upload
  - File validation (JPEG, PNG, GIF, WEBP)
  - Size limit (5MB max)
- **User Information**:
  - Name, email, role badges
  - Department badge
  - Active status indicator
- **Edit Profile Button**: Toggle edit mode

#### Statistics Cards
- Documents uploaded count
- Total activities (117)
- Member since date (Nov 2025)
- Last active time (13m ago)

#### Personal Information Section
- **Editable Fields**:
  - Full Name
  - Username
  - Email Address
  - Phone Number
  - Department
  - Position
- **Edit Mode**: Toggle to enable/disable field editing
- **Save/Cancel Buttons**: Appear when in edit mode

#### Account Security Section
- **Change Password**: Modal with validation
  - Current password
  - New password
  - Confirm password
  - Minimum 6 characters validation
  - Password matching check
- **Two-Factor Authentication**: Placeholder for future feature
- **Login History**: Link to audit logs

#### Recent Activity
- Last 5 user activities
- Activity type icons
- Timestamp display
- Link to view all in audit logs

#### Quick Links
- Account Settings
- My Documents
- Help Center

### 📤 Document Upload
- **Modal Form** with comprehensive fields:
  - Document reference number
  - Document type selection
  - Title
  - Date
  - Status (Draft, Pending, Approved)
  - Description
  - Tags (comma-separated)
- **Drag & Drop Zone**: Visual file upload area
- **File Input**: Traditional file selection
- **Form Validation**: Required field checking
- **Success Notifications**: Confirmation after upload
- **Automatic Audit Logging**: Upload action is logged

### 🔔 Notifications System
- **Real-time Notifications**: Toast-style notifications
- **Notification Types**:
  - Success (Green)
  - Error (Red)
  - Info (Blue)
  - Warning (Yellow)
- **Auto-dismiss**: Notifications fade out after 3 seconds
- **Notification Dropdown**: Bell icon with unread badge
- **Notification List**: Document uploads, approvals, user registrations

### 🎯 Additional Features
- **Quick Search**: Header search bar with Ctrl+K shortcut
- **Theme Toggle**: Dark mode button (placeholder)
- **Profile Menu**: Dropdown with user options
- **Modal System**: Reusable modals for uploads and viewing
- **Responsive Tables**: Mobile-friendly table views
- **Loading States**: Visual feedback during operations
- **Error Handling**: User-friendly error messages

## File Structure

```
capstone template/
├── system-template-full.html    # Main HTML file with complete structure
├── app-features.js              # All JavaScript functionality (1743 lines)
├── styles.css                   # Enhanced styles with animations
├── script.js                    # Basic UI interactions
├── cropped-VLogo.png           # Valenzuela City logo
└── README.md                    # This file
```

## Technologies Used

- **HTML5**: Semantic markup
- **CSS3**: Modern styling with animations
- **Tailwind CSS**: Utility-first framework (via CDN)
- **JavaScript (ES6+)**: Pure vanilla JavaScript, no frameworks
- **Chart.js**: Data visualization library (via CDN)
- **Bootstrap Icons**: Icon library (via CDN)

## Data Management

All data is stored **in-memory** using JavaScript objects:

```javascript
AppData = {
    documents: [],      // Document records
    users: [],         // User accounts
    notifications: [], // Notification items
    auditLogs: [],    // Activity logs
    currentUser: {}    // Active user session
}
```

**Note**: Data does **not persist** on page reload. For persistent storage, you can:
1. Add localStorage integration
2. Connect to a backend API
3. Use IndexedDB for client-side database

## Installation & Usage

### Option 1: Direct Open
1. Download all files to a folder
2. Open `system-template-full.html` in a modern web browser
3. No server required!

### Option 2: Local Server (Recommended)
```bash
# Using Python 3
python -m http.server 8000

# Using Node.js http-server
npx http-server

# Using PHP
php -S localhost:8000
```

Then open `http://localhost:8000/system-template-full.html`

## Sample Data

The template comes pre-loaded with sample data:

- **4 Documents**: Various types (Ordinance, Resolution, Session, Agenda)
- **4 Users**: Different roles (Administrator, Officer, Staff, Viewer)
- **3 Notifications**: Document and user activities
- **4 Audit Logs**: System activities

## Customization

### Change Color Theme
Edit the red colors in CSS:
```css
/* Primary Red */
#dc2626 → Your color
#b91c1c → Your darker shade
from-red-600 to-red-800 → Your gradient
```

### Add More Document Types
Update the dropdown options in:
```javascript
// In app-features.js, initializeData() function
type: 'ordinance' | 'resolution' | 'session' | 'agenda' | 'your-type'
```

### Modify User Roles
Update the roles array:
```javascript
role: 'Administrator' | 'Officer' | 'Staff' | 'Viewer' | 'Your-Role'
```

## Browser Compatibility

- ✅ Chrome 90+
- ✅ Firefox 88+
- ✅ Safari 14+
- ✅ Edge 90+
- ⚠️ IE 11 (Not recommended, limited support)

## Features Comparison

| Feature | LLRM System (PHP) | Capstone Template |
|---------|------------------|-------------------|
| Dashboard | ✅ | ✅ |
| Document Management | ✅ | ✅ |
| Advanced Search | ✅ | ✅ |
| Reports & Analytics | ✅ | ✅ |
| User Management | ✅ | ✅ |
| Audit Logs | ✅ | ✅ |
| Profile Management | ✅ | ✅ |
| Profile Picture Upload | ✅ | ✅ |
| Database Persistence | ✅ | ❌ (In-memory only) |
| User Authentication | ✅ | ❌ (Single user) |
| File Storage | ✅ | ❌ (Simulated) |
| Email Notifications | ✅ | ❌ |
| PDF Generation | ✅ | ❌ (Can be added) |

## Known Limitations

1. **No Data Persistence**: Data is lost on page refresh
2. **Single User**: No multi-user authentication
3. **No File Upload**: Files are simulated, not actually uploaded
4. **No Server-Side Processing**: All operations are client-side
5. **Limited Security**: No encryption or authentication

## Future Enhancements

- [ ] Add localStorage for data persistence
- [ ] Implement IndexedDB for better storage
- [ ] Add client-side PDF generation (jsPDF)
- [ ] Export to Excel functionality (SheetJS)
- [ ] Print-friendly views
- [ ] Dark mode implementation
- [ ] PWA (Progressive Web App) support
- [ ] Offline functionality
- [ ] Multi-language support

## Credits

**Developed for**: Valenzuela City Local Government Unit  
**Based on**: LLRM System (Legislative Records Management System)  
**Design**: Modern red theme with Tailwind CSS  
**Icons**: Bootstrap Icons  
**Charts**: Chart.js  

## License

This is a demonstration/educational template. For production use, please implement proper backend security and data persistence.

## Support

For issues or questions:
1. Check the browser console for errors
2. Ensure all files are in the same directory
3. Use a modern browser (Chrome, Firefox, Edge)
4. Check that CDN links are accessible

---

## Latest Updates (December 2025 - v2.0)

### NEW Updated Template Files
- **`system-template-updated.html`** - Latest template with all improvements
- **`styles-updated.css`** - Updated styles with dark mode, animations, and responsive design  
- **`script-updated.js`** - Updated JavaScript with mobile sidebar animations

### Mobile Sidebar Improvements
- ✅ Backdrop blur effect on overlay (`backdrop-blur-sm`)
- ✅ Smooth slide-in/out animations with cubic-bezier easing
- ✅ Staggered menu item animations (30ms delay per item)
- ✅ Close button with rotation animation on hover
- ✅ Compact profile section at bottom with side-by-side buttons
- ✅ Escape key to close sidebar
- ✅ Body scroll lock when sidebar is open

### Desktop Sidebar Enhancements
- ✅ Toggle button to collapse/expand sidebar
- ✅ Collapsed state saved to localStorage
- ✅ Hover effects on navigation items with translate animation

### Dark Mode Improvements
- ✅ Complete dark mode support across all components
- ✅ Filter checkbox labels visible in dark mode
- ✅ Date inputs styled for dark mode
- ✅ Tables and cards properly styled
- ✅ Custom scrollbar styling for dark mode

### Footer Updates
- ✅ Responsive footer layout
- ✅ Mobile: Compact layout with dot separators
- ✅ Desktop: Full layout with logo and links

### Documents Table Fixes
- ✅ Fixed table header alignment
- ✅ Desktop: Full table view with all columns
- ✅ Mobile: Card-based view with document info
- ✅ Drag-to-scroll functionality (DragScroll class)

### New Animations
- ✅ Fade-in animations for page elements
- ✅ Slide-in animations for cards
- ✅ Hover effects on stat cards
- ✅ Toast notifications with slide animation

---

**Last Updated**: December 2025  
**Version**: 2.0.0  
**Status**: Feature Complete with Enhanced Mobile Experience ✅
