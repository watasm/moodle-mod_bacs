// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License.
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Difficulty analysis module for BACS contests
 *
 * @module     mod_bacs/difficulty_analysis
 * @copyright  SybonTeam, sybon.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('mod_bacs/difficulty_analysis', ['jquery', 'core/ajax', 'core/notification', 'core/chartjs'], function($, Ajax, Notification, Chart) {
    let chartInstance = null;
    let currentCmid = null;
    let strings = {
        notasksselected: 'No tasks selected. Please add tasks to the contest first.',
        students_can_solve: 'Students who can solve the task',
        ideal_curve: 'Ideal curve',
        number_of_students: 'Number of students',
        tasks: 'Tasks'
    };

    /**
     * Collects task IDs from the tasks_reorder_list DOM element
     * @returns {Array} Array of task IDs
     */
    function collectTaskIds() {
        const tasksList = document.getElementById('tasks_reorder_list');
        const taskIds = [];
        
        if (tasksList && tasksList.children.length > 0) {
            for (let i = 0; i < tasksList.children.length; i++) {
                const taskElement = tasksList.children[i];
                // Try different ways to get task ID
                let taskId = null;
                if (taskElement.firstElementChild) {
                    taskId = taskElement.firstElementChild.innerHTML || taskElement.firstElementChild.textContent;
                } else if (taskElement.querySelector('.tasks_reorder_list_idholder')) {
                    taskId = taskElement.querySelector('.tasks_reorder_list_idholder').innerHTML || 
                             taskElement.querySelector('.tasks_reorder_list_idholder').textContent;
                }
                
                if (taskId) {
                    // Trim whitespace and convert to number
                    taskId = parseInt(taskId.toString().trim(), 10);
                    if (!isNaN(taskId) && taskId > 0) {
                        taskIds.push(taskId);
                    }
                }
            }
        }
        
        return taskIds;
    }

    /**
     * Updates the chart with current task list
     * @param {boolean} silent - If true, don't show loader/errors
     */
    function updateChart(silent) {
        if (!currentCmid) {
            console.log('bacsUpdateDifficultyChart: currentCmid not set');
            return;
        }

        const result = $('#bacs-difficulty-analysis-result');
        const loader = $('#bacs-difficulty-analysis-loader');
        const button = $('#bacs-difficulty-analysis-btn');

        // Check if chart container exists and is visible
        // Check multiple ways to determine visibility
        const resultElement = result[0];
        let isVisible = false;
        
        if (resultElement) {
            const style = window.getComputedStyle(resultElement);
            const isDisplayed = style.display !== 'none';
            const isNotHidden = style.visibility !== 'hidden';
            const hasOffsetParent = resultElement.offsetParent !== null;
            const jqueryVisible = result.is(':visible');
            
            // Element is visible if it's displayed, not hidden, and has offsetParent (or is body/html)
            isVisible = isDisplayed && isNotHidden && (hasOffsetParent || resultElement === document.body || resultElement === document.documentElement);
            
            // Also check jQuery's :visible as fallback
            if (!isVisible && jqueryVisible) {
                isVisible = true;
            }
            
            console.log('bacsUpdateDifficultyChart: visibility check', {
                isDisplayed: isDisplayed,
                isNotHidden: isNotHidden,
                hasOffsetParent: hasOffsetParent,
                jqueryVisible: jqueryVisible,
                finalVisible: isVisible,
                display: style.display,
                visibility: style.visibility
            });
        } else {
            console.log('bacsUpdateDifficultyChart: result element not found');
        }

        // Only update if chart is visible
        if (!isVisible) {
            console.log('bacsUpdateDifficultyChart: chart not visible, skipping update');
            return;
        }

        const taskIds = collectTaskIds();
        console.log('bacsUpdateDifficultyChart: collected task IDs:', taskIds);

        if (taskIds.length === 0) {
            console.log('bacsUpdateDifficultyChart: no task IDs found');
            if (!silent) {
                Notification.addNotification({
                    message: strings.notasksselected,
                    type: 'error'
                });
            }
            return;
        }

        // Show loader if not silent
        if (!silent) {
            loader.show();
        }

        console.log('bacsUpdateDifficultyChart: starting AJAX request with', taskIds.length, 'tasks');

        const sesskey = M.cfg.sesskey;

        $.ajax({
            url: M.cfg.wwwroot + '/mod/bacs/difficulty_analysis_ajax.php',
            type: 'POST',
            data: {
                cmid: currentCmid,
                task_ids: taskIds,
                sesskey: sesskey
            },
            traditional: false,
            dataType: 'json',
            success: function(response) {
                loader.hide();

                if (response.success) {
                    displayChart(response);
                } else {
                    if (!silent) {
                        Notification.addNotification({
                            message: response.error || 'Error analyzing contest difficulty',
                            type: 'error'
                        });
                    }
                }
            },
            error: function(xhr, status, error) {
                loader.hide();
                if (!silent) {
                    Notification.addNotification({
                        message: 'Error loading analysis: ' + error,
                        type: 'error'
                    });
                }
            }
        });
    }

    return {
        init: function(cmid, notasksselectedText, studentsCanSolveText, idealCurveText, numberOfStudentsText, tasksText) {
            // Store localized strings
            strings.notasksselected = notasksselectedText || 'No tasks selected. Please add tasks to the contest first.';
            strings.students_can_solve = studentsCanSolveText || 'Students who can solve the task';
            strings.ideal_curve = idealCurveText || 'Ideal curve';
            strings.number_of_students = numberOfStudentsText || 'Number of students';
            strings.tasks = tasksText || 'Tasks';
            currentCmid = cmid;
            
            const button = $('#bacs-difficulty-analysis-btn');
            const loader = $('#bacs-difficulty-analysis-loader');
            const result = $('#bacs-difficulty-analysis-result');
            const canvas = $('#bacs-difficulty-chart');

            button.on('click', function() {
                // Show loader, hide result
                loader.show();
                result.hide();
                button.prop('disabled', true);

                const taskIds = collectTaskIds();

                // Debug: log collected task IDs
                console.log('Collected task IDs:', taskIds);

                if (taskIds.length === 0) {
                    loader.hide();
                    button.prop('disabled', false);
                    Notification.addNotification({
                        message: strings.notasksselected,
                        type: 'error'
                    });
                    return;
                }

                // Get sesskey
                const sesskey = M.cfg.sesskey;

                // Make AJAX request with task IDs
                $.ajax({
                    url: M.cfg.wwwroot + '/mod/bacs/difficulty_analysis_ajax.php',
                    type: 'POST',
                    data: {
                        cmid: cmid,
                        task_ids: taskIds,
                        sesskey: sesskey
                    },
                    traditional: false,
                    dataType: 'json',
                    success: function(response) {
                        loader.hide();
                        button.prop('disabled', false);

                        if (response.success) {
                            // Display chart
                            result.show();
                            displayChart(response);
                        } else {
                            Notification.addNotification({
                                message: response.error || 'Error analyzing contest difficulty',
                                type: 'error'
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        loader.hide();
                        button.prop('disabled', false);
                        Notification.addNotification({
                            message: 'Error loading analysis: ' + error,
                            type: 'error'
                        });
                    }
                });
            });

            // Export update function to global scope for use by manage_tasks.js
            window.bacsUpdateDifficultyChart = function() {
                console.log('window.bacsUpdateDifficultyChart called');
                updateChart(true); // Silent update (no loader/errors)
            };
            
            // Also make it available immediately
            console.log('bacs difficulty_analysis module initialized, cmid:', cmid);
        }
    };

    function displayChart(data) {
        const ctx = document.getElementById('bacs-difficulty-chart');
        if (!ctx) {
            return;
        }

        // Destroy existing chart if it exists
        if (chartInstance) {
            chartInstance.destroy();
        }

        // Prepare data
        const taskLabels = data.task_labels;
        const studentsCanSolve = data.students_can_solve;
        const idealCurve = data.ideal_curve;

        // Create chart
        chartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: taskLabels,
                datasets: [
                    {
                        label: strings.students_can_solve,
                        data: studentsCanSolve,
                        backgroundColor: 'rgba(54, 162, 235, 0.6)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    },
                    {
                        label: strings.ideal_curve,
                        data: idealCurve,
                        type: 'line',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        borderWidth: 2,
                        fill: false,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: strings.number_of_students
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: strings.tasks
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                }
            }
        });
    }
});

