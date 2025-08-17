## Overview

The Drug Search and Tracker API is a Laravel-based RESTful API that enables users to search for drug information using the National Library of Medicine's RxNorm APIs and manage a personal medication list. The API supports user authentication, public drug searches, and private medication management with rate limiting, caching, and robust error handling. This project is designed for scalability, security, and ease of use, with comprehensive unit tests and API documentation.

## Features  
  
- User Authentication: Register and log in users with token-based authentication (Laravel Sanctum).
- Public Drug Search: Search for drugs by name, returning up to 5 results with RxCUI, name, ingredient base names, and dosage forms.
- Private Medication Management: Add, delete, and list user-specific medications (authenticated).
- Rate Limiting: Limits public search endpoint to 10 requests per minute to prevent abuse.
- Caching: Caches RxNorm API responses for 1 hour to improve performance.
- Unit Tests: Includes tests targeting 90%+ coverage for key functionalities.
- API Documentation: Postman collection for easy endpoint testing. Check the "Housecall API.postman_collection.json"
  
## Prerequisites  
  
- PHP: 8.1 or higher
- Composer: Latest version
- Database: SQLite (recommended for simplicity) or MySQL/PostgreSQL
- Laravel: 12.x
- Git: For cloning the repository
- Postman: For testing API endpoints

## Local Usage

### Commands
- Composer setup: `composer install`
- Serve localhost: `php artisan serve`
- Run test with coverage: `php artisan test --coverage` Or, `XDEBUG_MODE=coverage php artisan test --coverage`

