# Bot Panel

Bot Panel is a web application for managing Telegram bots, created using Laravel, Vue.js, Laravel Sanctum, and Vuex.

## [Try the bot panel now!](https://www.calories365.space)
### Test Access:
- **Login**: admin@example.com
- **Password**: admin@example.com

## Features

- **Management of different types of bots**: Set up and manage various types of bots, including regular bots for sending messages and photos, request bots for distributing requests among managers, multi-stage request bots allowing for step-by-step request formation and manager selection, and confirmation bots using phone numbers and external services.
- **Management of administrators and managers**: Add and manage administrators and managers of bots, distributing access and roles in bot management.
- **Monitoring and statistics**: Track the activity and effectiveness of each bot through detailed statistics and reports.
- **Data export**: Export user data by bots for analysis and reporting.
- **Notification broadcasting**: Use the notification system for efficient distribution of requests and informing users.

## Frontend Technologies

- **Vue.js**: The main framework for creating the user interface, including Vue 3 with the Composition API for state management. Integration of Vuex for centralized state management and Vue Router for navigation management.

- **Component architecture with configuration files**:
    - **Dynamic component generation**: Manage components through configuration files, allowing easy setup of structure, field types, and actions without altering the core code.
    - **Examples of tables and forms**: Configuration files for tables and forms describe columns, data types, validation, and actions, providing flexibility and scalability of the interface.

- **AdminLTE**: Use of Admin LTE to create an adaptive and functional administrative interface, offering a rich set of ready-made components and layouts.

## Backend Technologies

- **Laravel**: The main framework for backend development, providing powerful capabilities for creating RESTful APIs, data processing, and database management. Laravel Sanctum is used for authentication and resource protection.

- **Design Patterns**:
    - **Strategy**: Used to dynamically determine the logic of various bots, allowing modular and easy expansion of application functionality.
    - **Late Static Binding**: Used for flexible configuration of bot responses. This approach determines whether to use common responses to messages or custom responses for each bot, depending on the methods declared in the classes.

- **MySQL Database**: Storage and management of all information about users, bots, and their activities. Use of structured data schemas and optimized queries for efficient application operation.

- **Laravel Queues**: Used for asynchronous task processing, such as sending notifications and processing requests. This improves application performance by minimizing delays and distributing workload.

- **Security**: Modern security practices are applied to protect data and API interfaces, including protection against XSS and CSRF attacks, and data encryption.
