# BACS setting up a contest

This file contains information about the types of contest parameters and their settings.

### Creating contest:

1. Open your Moodle course page.
2. Turn on an Edit mode.
3. Click "Add an activity or resource" on your section.
4. Choose BACS contests and click "Add".
5. Enter a name for the new contest.
6. Select tasks for your contest.
7. Click one of the save buttons to complete the creation of the contest.

### Going to the contest settings:

You can do this in the following ways:
- Open your Moodle course page in Edit mode, click on the three dots near the contest and select "Edit settings".
- Open your contest page (not necessarily in Edit mode) and choose one of the  Settings buttons.

### Main contest settings:

The main contest settings are located at the top of the settings form:
1. Contest name is a string that will be displayed on all pages related to this contest.
2. Contest mode is a contest evaluation system. Check [evaluation system](Evaluation%20System.md) file for more information.
3. Start time and End time of the contest are the time frames during which the main participation in the contest is expected.
4. Virtual participation is an opportunity for each participant to write a contest at their own independent time. Check the following header for more information.
5. Allow upsolving checkbox allows you to solve tasks after the set End time.
6. Allow problem solving before the contest beginning checkbox allows you to solve tasks before the set Start time.
7. Isolate participants checkbox prohibits participants from seeing the statistics of others.

### Start time settings:

Conventionally, there are three options to set the start times for participants: a common unified start time in the general contest settings (item 3 from the list above), separate time settings for each group and a virtual contest. When the last option is enabled, after the start of the contest, the participants will have a button Start virtual participation now. So each participant to choose their own personal start time of the contest and in the monitor its results will be counted from this time mark. 

### Task settings:

In the Tasks section of the settings, you can configure the contest tasks and their order. The interface of this section consists of two parts:

- The first part of the interface is a list of all the tasks of this contest, under the inscription “Tasks of the contest”.

This is a list of all the tasks of the contest in the order in which they will be presented in the contest. Initially, when creating a contest, this list is empty, and when editing a contest, there will be already configured tasks. These tasks are numbered in Latin letters from A to Z. The number of tasks in the contest is limited to 26. You cannot add two identical tasks to the contest, so all the tasks in the contest are different.

- The second part of the interface is a table with all available tasks in the system.

Here you can view information about the available tasks of the contest: the task ID, its name, the format of its conditions, the author or authors of the task. To open a task condition, click on its name in the table. To add a task to the contest, click on the “Add” button in the “Actions” column in the row of the desired task.

### Task score settings:

For the IOI and General contest assessment modes, it is possible to set up points that will receive full and partial solutions to problems. __You set up points for only one current contest.__ To go to the settings of a specific task, select this task from the drop-down list:
- For each test of the problem, an non-negative integer of points is indicated, which the solution will receive if this test is passed successfully.
- A single non-negative integer of points is indicated, which will be additionally awarded for a solution that has passed all the tests of this problem (a complete solution).

### Advanced contest settings:

The task editor and score editor save (or encode) all information about the settings in a specific format for subsequent data transmission via an HTML form. If you want to view or change this data directly, bypassing visual editors, then you have the opportunity to do so in this section of the settings. There are two lines in this section:
- The encoded Task ID string is information from the contest task editor.
- The encoded string of Test scores is information from the score editor.

### Common module settings:

In addition to the standard settings, the module has its own group system.
If the group mode is enabled for the course, then there will also be settings for individual groups. These settings allow each group to set its own start and end time of the contest, as well as the settings for presolving and upsolving. The group user rights scheme is presented below:

|    | Groups disabled | Visible groups | Separate groups accessallgroups = true | Separate groups accessallgroups = false |
|---|----|----|---|---|
| No available group | General contest settings used | Use general contest settings, show all results, sending forbidden => message to select group | Use general contest settings, show all results | Forbidden, error message |
| All participants | Not reachable | Min start time for every user, results of all groups, sending forbidden => message to select group | Min start time for every user, results of all groups | Not reachable, group_id changes automaticly |
| My group | Not reachable | Settings and results of given group | Settings and results of given group | Settings and results of given group |
| Other group | Not reachable | Settings and results of given group, sending forbidden => message to select group | Settings and results of given group | Not reachable, group_id changes automaticly |
