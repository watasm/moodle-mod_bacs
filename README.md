# BACS contests

This is Moodle activity plugin for automatic solution judgment for programming tasks and for integrating programming contests into Moodle courses.

### Installation:

1. Copy the module code directly to the *moodleroot/mod/bacs* directory.
2. Go to http://your-moodle/admin (Site administration -> Notifications) to trigger the installation process.
3. Replace NoDefaultKey with your Sybon API key to access the tasks collections. If necessary, configure other default settings.
4. Use in any course as wished.

### Creating contest:

1. Open your Moodle course page.
2. Turn on an Edit mode.
3. Click "Add an activity or resource" on your section.
4. Choose BACS contests and click "Add".
5. Enter a name for the new contest.
6. Select tasks for your contest.
7. Click one of the save buttons to complete the creation of the contest.

Check [contest settings](mds/Contest%20Settings.md) file for more information.

### Plugin Features:

- Sending solutions and viewing their results, including execution time and memory consumption on all tests, as well as input/output data on pretests (open to test participants).
- The contest monitor is a summary table of the results of all participants on all tasks of the contest.
- Time limits for contests, upsolving and presolving settings.
- Several contest evaluation systems: IOI, ICPC, General.
- Score settings for each task in the contest (for scoring systems that work with scores).
- The ability to change the evaluation system of the contest, the set of tasks of the contest, the order of the tasks of the contest, as well as the scores for each of the tasks, even at any time. Even during the contest or after its completion.
- Support for groups of students.
- Virtual contests that allow each student to participate in the contest at an independent time.
- The ability to double-check, recalculate points, reject and change the results of parcels.

### Solving problems in different programming languages:

Examples of solving the A+B problem in all available languages can be found [here](mds/Sample%20Solutions.md)