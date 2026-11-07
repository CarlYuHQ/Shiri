shiri.tourFactory = (function () {

    /**
     * Create a new tour with default settings
     * @param {string} tourName - The name of the tour
     * @returns {Object} - Shepherd tour object
     */
    var createTour = function(tourName) {
        return new Shepherd.Tour({
            useModalOverlay: true,
            defaultStepOptions: {
                classes: 'shepherd-theme-arrows',
                scrollTo: false,
                cancelIcon: {
                    enabled: true
                }
            },
            tourName: tourName
        });
    };

    /**
     * Register a tour completion
     * @param {string} tourName - The name of the tour that was completed
     */
    var registerTourCompletion = function(tourName) {
        shiri.helperRepository.updateUserModalSettings(tourName);

        // Track tour completion for analytics
        if (typeof _paq !== 'undefined') {
            _paq.push(['trackEvent', 'Tour', 'Completed', tourName]);
        }
    };

    /**
     * Get tour definitions for a specific tour
     * @param {string} tourName - The name of the tour
     * @returns {Array} - Array of tour step definitions
     */
    var getTourDefinition = function(tourName) {
        const tourDefinitions = {
            'myWorkDashboard': [
                {
                    id: "welcome-step",
                    title: "👋 " + shiri.i18n.__("tour.my_work.welcome_title"),
                    text: shiri.i18n.__("tour.my_work.welcome_text"),
                },
                {
                    id: "top-nav-step",
                    title: shiri.i18n.__("tour.my_work.work_modes_title"),
                    text: shiri.i18n.__("tour.my_work.work_modes_text"),
                    attachTo: { element: '.work-modes', on: 'bottom' }
                },
                {
                    id: "menu-step",
                    title: shiri.i18n.__("tour.left_navigation"),
                    text: shiri.i18n.__("tour.my_work.left_nav_text"),
                    attachTo: { element: '.leftpanel', on: 'right' }
                },
                {
                    id: "my-menu",
                    title: shiri.i18n.__("tour.my_work.profile_bar_title"),
                    text: shiri.i18n.__("tour.my_work.profile_bar_text"),
                    attachTo: { element: '.headmenu.pull-right', on: 'bottom' }
                },
                {
                    id: "dashboard-widgets",
                    title: shiri.i18n.__("tour.my_work.widgets_title"),
                    text: shiri.i18n.__("tour.my_work.widgets_text"),
                    attachTo: { element: '.primaryContent', on: 'top' }
                },
                {
                    id: "dashboard-widgets-dandd",
                    title: shiri.i18n.__("tour.my_work.customize_title"),
                    text: shiri.i18n.__("tour.my_work.customize_text"),
                    attachTo: { element: '#widget_wrapper_todos .grid-handler-top', on: 'bottom' }
                },
                {
                    id: "my-todo-widget",
                    title: shiri.i18n.__("tour.my_work.todos_title"),
                    text: shiri.i18n.__("tour.my_work.todos_text"),
                    attachTo: { element: '#widget_wrapper_todos', on: 'top' }
                },
                {
                    id: "my-todo-widget2",
                    title: shiri.i18n.__("tour.my_work.group_todos_title"),
                    text: shiri.i18n.__("tour.my_work.group_todos_text"),
                    attachTo: { element: '#yourToDoContainer > .clear', on: 'bottom' }
                },
                {
                    id: "my-todo-widget4",
                    title: shiri.i18n.__("tour.my_work.sort_todos_title"),
                    text: shiri.i18n.__("tour.my_work.sort_todos_text"),
                    attachTo: { element: '#yourToDoContainer .sortable-item:first-child', on: 'bottom' }
                },
                {
                    id: "my-todo-widget-timer",
                    title: shiri.i18n.__("tour.my_work.timer_title"),
                    text: shiri.i18n.__("tour.my_work.timer_text"),
                    attachTo: { element: '#yourToDoContainer .sortable-item:first-child .timerContainer', on: 'bottom' }
                },
                {
                    id: "my-todo-widget-complete",
                    title: shiri.i18n.__("tour.my_work.complete_task_title"),
                    text: shiri.i18n.__("tour.my_work.complete_task_text"),
                    attachTo: { element: '#yourToDoContainer .sortable-item:first-child .statusDropdown', on: 'bottom' }
                },
                {
                    id: "my-todo-widget-add",
                    title: shiri.i18n.__("tour.my_work.add_tasks_title"),
                    text: shiri.i18n.__("tour.my_work.add_tasks_text"),
                    attachTo: { element: '#yourToDoContainer .fa-circle-plus', on: 'bottom' }
                },
                {
                    id: "finish",
                    title: shiri.i18n.__("tour.congratulations"),
                    text: shiri.i18n.__("tour.my_work.finish_text").replace('%s', shiri.appUrl),
                },
            ],
            'projectDashboard': [
                {
                    id: "left-nav",
                    title: shiri.i18n.__("tour.project_dashboard.menu_title"),
                    text: shiri.i18n.__("tour.project_dashboard.menu_text"),
                    attachTo: { element: '.leftmenu ul', on: 'left' }
                },
                {
                    id: 'project-selector',
                    title: shiri.i18n.__("tour.project_dashboard.selector_title"),
                    text: shiri.i18n.__("tour.project_dashboard.selector_text"),
                    attachTo: { element: '.bigProjectSelector', on: 'bottom' }
                },
                {
                    id: 'project-checklist',
                    title: shiri.i18n.__("tour.project_dashboard.checklist_title"),
                    text: shiri.i18n.__("tour.project_dashboard.checklist_text"),
                    attachTo: { element: '#progressForm', on: 'bottom' }
                },
                {
                    id: 'project-status',
                    title: shiri.i18n.__("tour.project_dashboard.status_title"),
                    text: shiri.i18n.__("tour.project_dashboard.status_text"),
                    attachTo: { element: '.project-updates', on: 'left' }
                },
                {
                    id: 'project-progress',
                    title: shiri.i18n.__("tour.project_dashboard.progress_title"),
                    text: shiri.i18n.__("tour.project_dashboard.progress_text"),
                    attachTo: { element: '.project-progress', on: 'left' }
                },
                {
                    id: 'latest-tasks',
                    title: shiri.i18n.__("tour.project_dashboard.latest_tasks_title"),
                    text: shiri.i18n.__("tour.project_dashboard.latest_tasks_text"),
                    attachTo: { element: '.latest-todos', on: 'left' }
                },
                {
                    id: 'teams',
                    title: shiri.i18n.__("tour.project_dashboard.team_title"),
                    text: shiri.i18n.__("tour.project_dashboard.team_text"),
                    attachTo: { element: '.team-container', on: 'top' }
                },
                {
                    id: 'finished',
                    title: shiri.i18n.__("tour.congratulations"),
                    text: shiri.i18n.__("tour.project_dashboard.finish_text").replace('%s', shiri.appUrl),
                }
            ],
            'kanbanBoard': [
                {
                    id: 'kanban-overview',
                    title: shiri.i18n.__("tour.kanban_board.overview_title"),
                    text: shiri.i18n.__("tour.kanban_board.overview_text"),
                    attachTo: { element: '.kanban-board-wrapper', on: 'top' }
                },
                {
                    id: 'kanban-columns',
                    title: shiri.i18n.__("tour.kanban_board.workflow_title"),
                    text: shiri.i18n.__("tour.kanban_board.workflow_text"),
                    attachTo: { element: '.column', on: 'right' }
                },
                {
                    id: 'kanban-columns2',
                    title: shiri.i18n.__("tour.kanban_board.columns_title"),
                    text: shiri.i18n.__("tour.kanban_board.columns_text"),
                    attachTo: { element: '.column .widgettitle .inlineDropDownContainer', on: 'right' }
                },
                {
                    id: 'kanban-filter',
                    title: shiri.i18n.__("tour.kanban_board.filter_title"),
                    text: shiri.i18n.__("tour.kanban_board.filter_text"),
                    attachTo: { element: '.filterWrapper > .btn', on: 'bottom' }
                },
                {
                    id: 'kanban-group',
                    title: shiri.i18n.__("tour.kanban_board.swimlanes_title"),
                    text: shiri.i18n.__("tour.kanban_board.swimlanes_text"),
                    attachTo: { element: '.filterWrapper > .btn-group', on: 'bottom' }
                },
                {
                    id: 'kanban-sprints',
                    title: shiri.i18n.__("tour.kanban_board.sprints_title"),
                    text: shiri.i18n.__("tour.kanban_board.sprints_text"),
                    attachTo: { element: '.pageheader .dropdown', on: 'bottom' }
                },
                {
                    id: 'kanban-congrats',
                    title: shiri.i18n.__("tour.congratulations"),
                    text: shiri.i18n.__("tour.kanban_board.finish_text").replace('%s', shiri.appUrl),
                }
            ],
            'milestoneView': [
                {
                    id: 'milestone-overview',
                    title: shiri.i18n.__("tour.milestones.overview_title"),
                    text: shiri.i18n.__("tour.milestones.overview_text"),
                    attachTo: { element: '.gantt-wrapper', on: 'top' }
                },
                {
                    id: 'milestone-drag',
                    title: shiri.i18n.__("tour.milestones.drag_title"),
                    text: shiri.i18n.__("tour.milestones.drag_text"),
                    attachTo: { element: '.gantt-wrapper', on: 'top' }
                },
                {
                    id: 'milestone-filter',
                    title: shiri.i18n.__("tour.milestones.filter_title"),
                    text: shiri.i18n.__("tour.milestones.filter_text"),
                    attachTo: { element: '.filterWrapper > .btn', on: 'bottom' }
                },
                {
                    id: 'milestone-timeframes',
                    title: shiri.i18n.__("tour.milestones.timeframes_title"),
                    text: shiri.i18n.__("tour.milestones.timeframes_text"),
                    attachTo: { element: '.col-md-4 .pull-right', on: 'bottom' }
                },
                {
                    id: 'milestone-congrats',
                    title: shiri.i18n.__("tour.congratulations"),
                    text: shiri.i18n.__("tour.milestones.finish_text").replace('%s', shiri.appUrl),
                },
            ],
            'goalsView': [
                {
                    id: 'goals-overview',
                    title: shiri.i18n.__("tour.goals.overview_title"),
                    text: shiri.i18n.__("tour.goals.overview_text"),
                },
                {
                    id: 'goal-parts',
                    title: shiri.i18n.__("tour.goals.parts_title"),
                    text: shiri.i18n.__("tour.goals.parts_text"),
                    attachTo: { element: '.ticketBox', on: 'top' }
                },
                {
                    id: 'goal-progress',
                    title: shiri.i18n.__("tour.goals.progress_title"),
                    text: shiri.i18n.__("tour.goals.progress_text"),
                    attachTo: { element: '.ticketBox .row > .col-md-12 .progress', on: 'bottom' }
                },
                {
                    id: 'milestone-connection',
                    title: shiri.i18n.__("tour.goals.milestones_title"),
                    text: shiri.i18n.__("tour.goals.milestones_text"),
                    attachTo: { element: '.ticketBox.fixed', on: 'bottom' }
                },
                {
                    id: 'milestone-congrats',
                    title: shiri.i18n.__("tour.congratulations"),
                    text: shiri.i18n.__("tour.goals.finish_text").replace('%s', shiri.appUrl),
                },
            ]
        };

        return tourDefinitions[tourName] || [];
    };

    /**
     * Build a tour from a definition
     * @param {string} tourName - The name of the tour to build
     * @returns {Object} - Configured Shepherd tour object
     */
    var buildTour = function(tourName) {
        const tour = createTour(tourName);
        const steps = getTourDefinition(tourName);

        steps.forEach((step, index) => {
            const isFirst = index === 0;
            const isLast = index === steps.length - 1;

            // Configure buttons based on position in tour
            const buttons = [];

            if (!isFirst) {
                buttons.push({
                    text: shiri.i18n.__("tour.back"),
                    classes: 'shepherd-button-secondary',
                    action: tour.back
                });
            }

            if (isLast) {
                buttons.push({
                    text: shiri.i18n.__("tour.finish"),
                    action: function() {
                        registerTourCompletion(tourName);
                        confetti();
                        tour.complete();
                    }
                });
            } else {
                buttons.push({
                    text: shiri.i18n.__("tour.next"),
                    action: tour.next
                });
            }

            // Add cancel button for all steps
            if (!isLast) {
                buttons.unshift({
                    text: shiri.i18n.__("tour.cancel"),
                    classes: 'shepherd-button-secondary',
                    action: tour.cancel
                });
            }

            // Add the step to the tour
            tour.addStep({
                ...step,
                buttons: buttons
            });
        });

        // Add event handlers
        tour.on('complete', function() {
            registerTourCompletion(tourName);
        });

        return tour;
    };

    /**
     * Start a specific tour
     * @param {string} tourName - The name of the tour to start
     */
    var startTour = function(tourName) {
        const tour = buildTour(tourName);
        tour.start();
        return tour;
    };

    return {
        createTour: createTour,
        buildTour: buildTour,
        startTour: startTour,
        getTourDefinition: getTourDefinition
    };
})();
