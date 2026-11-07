shiri.usersService = (function () {

    //Functions

    var saveUserPhoto = function (photo) {
        shiri.usersRepository.saveUserPhoto(photo);
    };

    // Make public what you want to have public, everything else is private
    return {
        saveUserPhoto: saveUserPhoto
    };
})();
