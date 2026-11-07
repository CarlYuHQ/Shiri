shiri.settingService = (function () {

    // Variables (underscore for private variables)
    var publicThing = "not secret";
    var _privateThing = "secret";

    //Constructor
    (function () {

    })();

    //Functions

    var saveLogo = function (photo) {
        shiri.settingRepository.saveLogo(photo);
    };

    // Make public what you want to have public, everything else is private
    return {
        saveLogo: saveLogo
    };
})();
