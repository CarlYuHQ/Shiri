shiri.helperController = (function () {

    var setDontShowAgain = function(module, dontShow) {
        if (dontShow) {
            shiri.helperRepository.updateUserModalSettings(module, true);
        } else {
            shiri.helperRepository.updateUserModalSettings(module, false);
        }
    };

    var startMyWorkDashboardTour = function () {
        shiri.modals.setCustomModalCallback(function(){});
        shiri.modals.closeModal();

        // Use the tour factory instead
        return shiri.tourFactory.startTour('myWorkDashboard');
    };

    var closeModal = function() {
        shiri.modals.setCustomModalCallback(function(){});
        shiri.modals.closeModal();

    }

    var startProjectDashboardTour = function () {
        shiri.modals.setCustomModalCallback(function(){});
        shiri.modals.closeModal();

        shiri.helperRepository.updateUserModalSettings("projectDashboard");
        return shiri.tourFactory.startTour('projectDashboard');
    };

    var startKanbanTour = function () {
        if(jQuery.nmTop()) {
            jQuery.nmTop().close();
        }

        shiri.helperRepository.updateUserModalSettings("kanbanBoard");
        return shiri.tourFactory.startTour('kanbanBoard');
    };

    var startMilestoneTour = function () {
        if(jQuery.nmTop()) {
            jQuery.nmTop().close();
        }

        shiri.helperRepository.updateUserModalSettings("milestoneView");
        return shiri.tourFactory.startTour('milestoneView');
    };

    var startGoalTour = function () {
        shiri.modals.setCustomModalCallback(function(){});
        shiri.modals.closeModal();

        shiri.helperRepository.updateUserModalSettings("goals");
        return shiri.tourFactory.startTour('goalsView');
    };

    var firstLoginModal = function () {

        jQuery(document).ready(function () {

            var onboardingModal = {
                sizes: {
                    minW: 700,
                    minH: 250
                },
                resizable: true,
                autoSizable: true,
                callbacks: {
                    afterShowCont: function () {
                        jQuery(".showDialogOnLoad").show();
                        jQuery(".onboardingModal").nyroModal(onboardingModal);
                    },
                    beforeClose: function () {

                        location.reload();
                    },
                }
            };

            jQuery(".onboardingModal").nyroModal(onboardingModal);

            jQuery.nmManual(
                shiri.appUrl + "/help/firstLogin",
                onboardingModal
            );
        });
    };

    //Functions
    var showHelperModal = function (module, minW, minH) {

        jQuery(document).ready(function () {
            jQuery.nmManual(
                shiri.appUrl + "/help/showOnboardingDialog?module=" + module,
                {sizes: {
                        minW: minW || 200,
                        minH: minH || 500,
                    },
                    resizable: true,
                    autoSizable: true,
                    callbacks: {
                        beforeShowCont: function () {
                            shiri.replaceSVGColors();
                        },
                        afterShowCont: function() {
                            htmx.process(".nyroModalCont");
                        }
                    }
                }
            );
        });

    };

    var hideAndKeepHidden = function (module) {

        shiri.helperRepository.updateUserModalSettings(module);
        shiri.modals.setCustomModalCallback(function(){});
        shiri.modals.closeModal();

    };

    return {
        showHelperModal: showHelperModal,
        hideAndKeepHidden: hideAndKeepHidden,
        setDontShowAgain: setDontShowAgain,
        closeModal:closeModal,
        startMyWorkDashboardTour: startMyWorkDashboardTour,
        startProjectDashboardTour:startProjectDashboardTour,
        startKanbanTour: startKanbanTour,
        startMilestoneTour: startMilestoneTour,
        startGoalTour:startGoalTour,
        firstLoginModal:firstLoginModal
    };
})();
