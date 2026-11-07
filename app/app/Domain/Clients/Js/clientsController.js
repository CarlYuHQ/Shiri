shiri.clientsController = (function () {


    //Functions

    var initDates = function () {

        jQuery(".projectDateFrom, .projectDateTo").datepicker(
            {
                dateFormat:  shiri.i18n.__("language.dateformat"),
                dayNames: shiri.i18n.__("language.dayNames").split(","),
                dayNamesMin:  shiri.i18n.__("language.dayNamesMin").split(","),
                dayNamesShort: shiri.i18n.__("language.dayNamesShort").split(","),
                monthNames: shiri.i18n.__("language.monthNames").split(","),
                currentText: shiri.i18n.__("language.currentText"),
                closeText: shiri.i18n.__("language.closeText"),
                buttonText: shiri.i18n.__("language.buttonText"),
                isRTL: shiri.i18n.__("language.isRTL") === "true" ? 1 : 0,
                nextText: shiri.i18n.__("language.nextText"),
                prevText: shiri.i18n.__("language.prevText"),
                weekHeader: shiri.i18n.__("language.weekHeader"),
            }
        );
    };

    var initClientTabs = function () {
        jQuery('.clientTabs').tabs();
    };

    var initClientTable = function () {

        jQuery(document).ready(function () {

            var size = 100;

            var allProjects = jQuery("#allClientsTable").DataTable({
                "language": {
                    "decimal":        shiri.i18n.__("datatables.decimal"),
                    "emptyTable":     shiri.i18n.__("datatables.emptyTable"),
                    "info":           shiri.i18n.__("datatables.info"),
                    "infoEmpty":      shiri.i18n.__("datatables.infoEmpty"),
                    "infoFiltered":   shiri.i18n.__("datatables.infoFiltered"),
                    "infoPostFix":    shiri.i18n.__("datatables.infoPostFix"),
                    "thousands":      shiri.i18n.__("datatables.thousands"),
                    "lengthMenu":     shiri.i18n.__("datatables.lengthMenu"),
                    "loadingRecords": shiri.i18n.__("datatables.loadingRecords"),
                    "processing":     shiri.i18n.__("datatables.processing"),
                    "search":         shiri.i18n.__("datatables.search"),
                    "zeroRecords":    shiri.i18n.__("datatables.zeroRecords"),
                    "paginate": {
                        "first":      shiri.i18n.__("datatables.first"),
                        "last":       shiri.i18n.__("datatables.last"),
                        "next":       shiri.i18n.__("datatables.next"),
                        "previous":   shiri.i18n.__("datatables.previous"),
                    },
                    "aria": {
                        "sortAscending":  shiri.i18n.__("datatables.sortAscending"),
                        "sortDescending":shiri.i18n.__("datatables.sortDescending"),
                    }

                },
                "dom": '<"top">rt<"bottom"ilp><"clear">',
                "searching": false,
                "displayLength":100
            });

        });

    };

    // Make public what you want to have public, everything else is private
    return {
        initDates:initDates,
        initClientTabs:initClientTabs,
        initClientTable:initClientTable
    };
})();
