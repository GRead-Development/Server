# BuddyPress HotSoup Theme

A clean, modern WordPress theme optimized for BuddyPress and the HotSoup book tracking plugin. Simple, beautiful, and easy to customize.

## Features

- **BuddyPress Integration**: Full support for BuddyPress social networking features
- **HotSoup Plugin Support**: Seamless integration with HotSoup book tracking
- **Responsive Design**: Mobile-first design that works on all devices
- **Clean & Modern**: Simple, elegant design that puts content first
- **Fast & Lightweight**: Optimized for performance
- **Accessible**: Built with accessibility in mind
- **Easy to Customize**: Well-organized code and clear structure

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- BuddyPress plugin (recommended)
- HotSoup plugin (recommended)

## Installation

1. **Download the theme:**
   - Download the `buddypress-hotsoup-theme` folder

2. **Upload to WordPress:**
   - Upload the theme folder to `/wp-content/themes/`
   - Or use WordPress admin: Appearance > Themes > Add New > Upload Theme

3. **Activate the theme:**
   - Go to Appearance > Themes
   - Find "BuddyPress HotSoup Theme" and click Activate

4. **Configure theme settings:**
   - Go to Appearance > Customize to customize your theme
   - Set up your navigation menus
   - Add widgets to sidebars

## Theme Structure

```
buddypress-hotsoup-theme/
├── buddypress/           # BuddyPress template overrides
│   ├── activity/         # Activity stream templates
│   └── members/          # Member directory templates
├── css/                  # Stylesheets
│   └── hotsoup-integration.css  # HotSoup-specific styles
├── js/                   # JavaScript files
│   └── main.js           # Main theme JavaScript
├── functions.php         # Theme functions and features
├── header.php            # Header template
├── footer.php            # Footer template
├── sidebar.php           # Sidebar template
├── index.php             # Main template file
├── style.css             # Main stylesheet
└── README.md             # This file
```

## Customization

### Colors & Typography

Edit `style.css` to change colors, fonts, and other styling. The theme uses CSS custom properties for easy customization:

```css
/* Primary colors */
--primary-color: #0073aa;
--secondary-color: #005a87;
--text-color: #333;
--background-color: #f5f5f5;
```

### Menus

The theme supports two menu locations:
- **Primary Menu**: Main navigation in the header
- **Footer Menu**: Optional footer navigation

Set them up in: Appearance > Menus

### Widgets

The theme has two widget areas:
- **Sidebar**: Displays on all pages with sidebar
- **Footer**: Displays in the footer area

Add widgets in: Appearance > Widgets

### BuddyPress

The theme automatically styles BuddyPress components. To customize BuddyPress templates, edit files in the `buddypress/` folder.

### HotSoup Integration

HotSoup plugin features are automatically styled. Additional HotSoup styles are in `css/hotsoup-integration.css`.

## Features Included

### Layout
- Responsive, mobile-first design
- Flexible sidebar layout
- Clean, card-based design
- Sticky header navigation

### BuddyPress Features
- Activity streams
- Member profiles
- Member directories
- Groups (if enabled)
- Custom navigation

### HotSoup Features
- Book lists and directories
- Reading progress tracking
- Book reviews and ratings
- Theme selector integration
- Achievement displays
- Statistics displays

### JavaScript Features
- Mobile navigation toggle
- Smooth scrolling
- Form loading states
- Back to top button
- Lazy loading images
- Accessibility enhancements

## Browser Support

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)
- Mobile browsers (iOS Safari, Chrome Mobile)

## Accessibility

This theme follows WordPress accessibility standards and includes:
- Semantic HTML5 markup
- ARIA labels and roles
- Keyboard navigation support
- Skip to content link
- Color contrast compliance

## Performance

The theme is optimized for performance with:
- Minimal CSS and JavaScript
- Lazy loading for images
- Conditional script loading
- Efficient database queries
- No external dependencies

## Support

For issues, questions, or contributions:
- GitHub: https://github.com/GRead-Development

## Credits

- Developed by: GRead Development
- BuddyPress: https://buddypress.org/
- WordPress: https://wordpress.org/

## License

This theme is licensed under the GNU General Public License v2 or later.

## Changelog

### Version 1.0.0
- Initial release
- BuddyPress integration
- HotSoup plugin integration
- Responsive design
- Accessibility features
- Performance optimizations
