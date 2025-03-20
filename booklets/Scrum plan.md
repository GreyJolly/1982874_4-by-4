# Scrum Plan for 4-by-4 Platform Development

## Sprint Duration:

2 Weeks per Sprint

## Sprint 1: Foundation and Authentication
### Goal:
Set up the basic infrastructure, including the database, authentication system, and user registration/login functionality.
### Sprint Backlog:

#### Set up PostgreSQL database
Create users, games, and challenges tables.

Implement PDO for database interactions.

User Stories: 2, 3, 4, 5, 7, 8, 9

#### Implement Authentication Service

Create /login.php and /check-token.php endpoints.

Implement token-based session management.

User Stories: 2, 3, 4, 5

#### Develop Frontend for Login and Registration

Create Login and Register pages.

Integrate with Authentication Service.

User Stories: 2, 3, 6

#### Set up Redis for Session Storage

Configure Redis as the session handler for PHP.

User Stories: 4, 5

## Sprint 2: User Profile and Basic Game Setup
### Goal:

Implement user profile functionality and basic game setup, including challenge creation and joining.
Sprint Backlog:

### Sprint Backlog:

#### Implement Profile Handling Service

Create /profile.php and /update-profile.php endpoints.

User Stories: 7, 8, 9, 10, 11, 12, 13, 14, 15, 18

#### Develop Frontend for Profile Page 

Create Profile page.

Integrate with Profile Handling Service.

User Stories: 7, 8, 9, 10, 11, 12, 13, 14, 15, 18

#### Set up WebSocket Service

Implement WebSocket server for real-time communication.

User Stories: 1, 16, 17, 18, 19, 20

#### Develop Frontend for Challenge Creation and Joining

Create Index page for challenge creation and joining.

Integrate with WebSocket Service.

User Stories: 1, 16, 17, 18, 19, 20

## Sprint 3: Game Logic and Real-Time Play
### Goal:

Implement the core game logic, real-time gameplay, and basic game features.

### Sprint Backlog:

#### Implement Game Logic in WebSocket Service

Handle game state, turns, and win conditions.

User Stories: 1, 21, 22, 24, 25, 26, 27, 28, 29

#### Develop Frontend for Game Page

Create Game page.

Implement real-time updates via WebSocket.

User Stories: 1, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32

#### Implement Chat Feature

Add real-time chat functionality to WebSocket Service.

User Stories: 23

#### Implement Timing Settings

Add timing settings (blitz, rapid, custom) to game logic.

User Stories: 20, 24

## Sprint 4: Replays, Statistics, and Polish
### Goal:

Implement game replays, statistics, and final polish for the platform.

### Sprint Backlog:

#### Implement Replay Functionality

Store move sequences in the database.

Create /replay.php endpoint.

User Stories: 12, 14, 30, 33, 34, 35, 36, 37

### Develop Frontend for Replay Page

Create Replay page.

Implement move-by-move replay functionality.

User Stories: 12, 14, 30, 33, 34, 35, 36, 37

### Implement Aggregate Statistics

Add statistics calculation to Profile Handling Service.

User Stories: 10, 11, 15

### Polish and Bug Fixes

Address any remaining bugs and improve UI/UX.

User Stories: All

## Sprint 5: Testing and Deployment
### Goal:

Conduct thorough testing, fix any remaining issues, and prepare for deployment.
### Sprint Backlog:

#### End-to-End Testing

Test all user stories and edge cases.

User Stories: All

#### Performance Optimization

Optimize database queries and WebSocket performance.

User Stories: All

#### Deployment Preparation

Prepare the platform for deployment (e.g., Docker setup, environment configuration).

User Stories: All

#### Final Documentation

Write final documentation for the platform.

User Stories: All
