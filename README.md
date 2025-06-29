# Anime Website Project

## Project Overview
This is a PHP-based anime streaming website with admin capabilities, SEO management, and analytics tracking. The project uses SQLite databases for data storage and includes features for managing anime content, episodes, backgrounds, and SEO optimization.

## Technology Stack
- PHP
- SQLite3 Databases
- HTML/CSS (Frontend)
- JSON for data storage

## Database Structure
The project uses multiple SQLite databases:
- `anime_database.db`: Main database for anime information
- `anime_episodes.db`: Database for episode management
- `analytics.db`: Tracks visitor analytics
- `seo.db`: Manages SEO-related data

## Key Features
1. **Content Management**
   - Add/manage anime titles
   - Episode management
   - Background customization
   - SEO optimization tools

2. **Admin Features**
   - Admin dashboard (`admin.php`)
   - Analytics tracking
   - SEO management interface
   - Content moderation tools

3. **User Features**
   - Anime browsing
   - Episode watching
   - Search functionality
   - Responsive design

## File Structure Overview
```
anime-website/
├── Admin Management
│   ├── admin.php
│   ├── manage-anime.php
│   ├── manage-episodes.php
│   └── manage-backgrounds.php
├── SEO Tools
│   ├── dashboard_seo.php
│   ├── manage-seo.php
│   ├── update-seo.php
│   └── delete-seo.php
├── Analytics
│   ├── analytics.php
│   └── create_analytics_db.php
├── Core Features
│   ├── index.php
│   ├── details.php
│   ├── watch-episode.php
│   └── search.php
└── Includes
    ├── header.php
    ├── schema.php
    └── track_visit.php
```

## Important Notes for Cursor
1. **Database Integration**
   - Multiple SQLite databases are used
   - Database schema is defined in `db_structure.php`
   - JSON data storage is used for episodes (`anime_episodes.json`)

2. **SEO Implementation**
   - Schema markup in `includes/schema.php`
   - Sitemap generation in `sitemap.php`
   - robots.txt configuration present

3. **Analytics System**
   - Visit tracking implemented in `includes/track_visit.php`
   - Analytics dashboard in `analytics.php`

4. **Content Management**
   - Episode management system
   - Background customization features
   - Anime details management

## Development Guidelines
1. **Database Operations**
   - Always use prepared statements for database queries
   - Check database connections before operations
   - Follow the existing database schema

2. **SEO Practices**
   - Maintain schema markup structure
   - Update sitemap when adding new content
   - Follow robots.txt directives

3. **Security Considerations**
   - Validate all user inputs
   - Sanitize database queries
   - Implement proper access controls for admin features

## Setup Instructions
1. Ensure PHP and SQLite3 are installed
2. Place the project in your web server directory
3. Set up database files using the creation scripts:
   - Run `create_analytics_db.php`
   - Run `create_seo_db.php`
4. Configure any necessary permissions
5. Access the admin panel to start adding content

## Future Development Areas
1. User authentication system
2. API integration for automated content updates
3. Enhanced analytics reporting
4. Mobile app integration
5. Content recommendation system

## Known Issues
- Document any specific issues or limitations that need attention
- Note any pending features or improvements
- List any compatibility concerns

This README provides essential information for Cursor to understand the project structure and generate more accurate and context-aware code suggestions. Make sure to update this documentation as the project evolves. 