var shiri = shiri || {};

shiri.helperRepository = (function () {

    //Functions

    var updateUserModalSettings = function (module, permanent = false) {

        shiri.rpc('Users.Users.saveModalDismissal', { modalKey: module, permanent: !!permanent })
            .catch(function (e) { console.error('Could not save modal setting', e); });

    };

    var startingTour = function () {

        shiri.rpc('Api.Api.setTourActive', { tourActive: 1 })
            .catch(function (e) { console.error('Could not start tour', e); });

    };

    var stopTour = function () {

        shiri.rpc('Api.Api.setTourActive', { tourActive: 0 })
            .catch(function (e) { console.error('Could not stop tour', e); });

    };



    // Make public what you want to have public, everything else is private
    return {
        updateUserModalSettings: updateUserModalSettings,
        startingTour:startingTour,
        stopTour:stopTour
    };
})();
