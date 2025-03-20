# Function Points Analysis
The analysis is divided into External Inputs (EI), External Outputs (EO), External Inquiries (EQ), and Internal Logical Files (ILF).
## External Inputs (EI)
External Inputs are processes where data is entered into the system from outside.

User Story|Description|Complexity|FP Count
---|---|---|---
2|Register a new user account|Low|3
3|Log in to the site|Low|3
5|Log out of the site|Low|3
8|Change username|Low|3
9|Change password|Low|3
19|Set board size when creating a challenge|Low|3
20|Set timing settings for a challenge|Low|3
25|Place a piece on the grid|Low|3
26|Concede a game|Low|3
27|Offer a draw|Low|3
28|Retire a draw offer|Low|3
29|Deny a draw offer|Low|3
Total EI|||33

## External Outputs (EO)
External Outputs are processes where data is sent outside the system (e.g., reports, notifications).

User Story|Description|Complexity|FP Count
---|---|---|---
1|Play Connect Four online against other users|High|6
6|Display navigation buttons on all pages|Low|4
7|Display user profile details|Low|4
10|Display aggregate statistics|Medium|5
11|Display winners of previous games|Medium|5
12|Display replays of previous matches|High|6
13|Display settings of previous games|Medium|5
14|Display replays of other players' games|High|6
15|Display creator of previous matches|Medium|5
16|Display active challenges|Medium|5
17|Display challenge creator|Medium|5
18|Display challenge creator's profile|Medium|5
21|Automatically handle game logic|High|6
22|Display whose turn it is|Low|4
23|Display chat messages|Medium|5
24|Display time left for players|Medium|5
30|Display match replay after game ends|High|6
31|Display game result and how it ended|Medium|5
32|Display exit button after game ends|Low|4
33|Display button to go back to profile from replay|Low|4
34|Display number of moves in a replay|Medium|5
35|Display replay move-by-move|High|6
36|Allow going back to previous move in replay|Medium|5
37|Display game result and how it ended (beginner-friendly)|Medium|5
Total EO|||118

## External Inquiries (EQ)

External Inquiries are processes where the system retrieves and displays data without modifying it.

User Story|Description|Complexity|FP Count
---|---|---|---
4|Remember user credentials|Low|3
7|Retrieve user profile details|Low|3
10|Retrieve aggregate statistics|Medium|4
11|Retrieve winners of previous games|Medium|4
12|Retrieve replays of previous matches|High|6
13|Retrieve settings of previous games|Medium|4
14|Retrieve replays of other players' games|High|6
15|Retrieve creator of previous matches|Medium|4
16|Retrieve active challenges|Medium|4
17|Retrieve challenge creator|Medium|4
18|Retrieve challenge creator's profile|Medium|4
22|Retrieve whose turn it is|Low|3
23|Retrieve chat messages|Medium|4
24|Retrieve time left for players|Medium|4
30|Retrieve match replay after game ends|High|6
31|Retrieve game result and how it ended|Medium|4
34|Retrieve number of moves in a replay|Medium|4
35|Retrieve replay move-by-move|High|6
36|Retrieve previous move in replay|Medium|4
37|Retrieve game result and how it ended (beginner-friendly)|Medium|4
Total EQ|||83

##  Internal Logical Files (ILF)
Internal Logical Files are data stored within the system that is maintained by the application.

User Story|Description|Complexity|FP Count
---|---|---|---
2|Store user account data|Medium|7
3|Store login session data|Medium|7
5|Store logout session data|Medium|7
7|Store user profile data|Medium|7
8|Store updated username|Medium|7
9|Store updated password|Medium|7
10|Store aggregate statistics|High|10
11|Store game history data|High|10
12|Store replay data|High|10
13|Store game settings data|Medium|7
14|Store other players' replay data|High|10
15|Store match creator data|Medium|7
16|Store active challenges data|Medium|7
17|Store challenge creator data|Medium|7
18|Store challenge creator's profile data|Medium|7
19|Store board size settings|Medium|7
20|Store timing settings|Medium|7
21|Store game logic data|High|10
22|Store turn data|Medium|7
23|Store chat messages|Medium|7
24|Store time data|Medium|7
25|Store piece placement data|Medium|7
26|Store concede data|Medium|7
27|Store draw offer data|Medium|7
28|Store draw offer retirement data|Medium|7
29|Store draw offer denial data|Medium|7
30|Store replay data after game ends|High|10
31|Store game result data|Medium|7
32|Store exit button state|Low|5
33|Store replay navigation data|Medium|7
34|Store move count data|Medium|7
35|Store replay move data|High|10
36|Store previous move data|Medium|7
37|Store beginner-friendly game result data|Medium|7
Total ILF|||253

## Total Function Points (FP)
Category|FP Count
---|---
External Inputs (EI)|33
External Outputs (EO)|118
External Inquiries (EQ)|83
Internal Logical Files (ILF)|253
Total FP|487
