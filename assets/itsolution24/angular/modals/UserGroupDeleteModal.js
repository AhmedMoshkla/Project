window.angularApp.factory("UserGroupDeleteModal", ["API_URL", "window", "jQuery", "$http", "$uibModal", "$sce", "$rootScope", function (API_URL, window, $, $http, $uibModal, $sce, $scope) {
    return function(usergroup) {
        var uibModalInstance = $uibModal.open({
            animation: true,
            ariaLabelledBy: "modal-title",
            ariaDescribedBy: "modal-body",
            template: "<div class=\"modal-header\">" +
                        "<button ng-click=\"closeUsergroupDelteModal();\" type=\"button\" class=\"close\" data-dismiss=\"modal\" aria-label=\"Close\"><span aria-hidden=\"true\">&times;</span></button>" +
                       "<h3 class=\"modal-title\" id=\"modal-title\"><span class=\"fa fa-fw fa-trash\"></span> {{ modal_title }}</h3>" +
                        "</div>" +
                        "<div class=\"modal-body\" id=\"modal-body\">" +
                        "<div bind-html-compile=\"rawHtml\"><span class=\"modal-loader\">Loading...</span></div>" +
                    "</div>",
            controller: function ($scope, $uibModalInstance) {
                $http({
                  url: window.baseUrl + "/_inc/user_group.php?group_id=" + usergroup.group_id + "&action_type=DELETE",
                  method: "GET"
                })
                .then(function(response, status, headers, config) {
                    $scope.modal_title = usergroup.name;
                    $scope.rawHtml = $sce.trustAsHtml(response.data);

                    setTimeout(function() {
                        window.storeApp.select2();
                    }, 100);
                    
                }, function(response) {
                    window.swal("Oops!", response.data.errorMsg, "error")
                    .then(function() {
                        $scope.closeUsergroupDelteModal();
                    });
                });

                $(document).delegate("#user-group-delete", "click", function(e) {

                    e.stopImmediatePropagation();
                    e.stopPropagation();
                    e.preventDefault();

                    var $tag = $(this);
                    var $btn = $tag.button("loading");
                    var form = $($tag.data("form"));
                    var datatable = $tag.data("datatable");
                    form.find(".alert").remove();
                    var actionUrl = form.attr("action");
                    $http({
                        url: window.baseUrl + "/_inc/" + actionUrl,
                        method: "POST",
                        data: form.serialize(),
                        cache: false,
                        processData: false,
                        contentType: false,
                        dataType: "json"
                    }).
                    then(function(response) {

                        $btn.button("reset");
                        var alertMsg = "<div class=\"alert alert-success\">";
                        alertMsg += "<p><i class=\"fa fa-check\"></i> " + response.data.msg + ".</p>";
                        alertMsg += "</div>";
                        form.find(".box-body").before(alertMsg);
                        $(datatable).DataTable().ajax.reload( null, false );

                        // Alert
                        window.swal("Success", response.data.msg, "success")
                        .then(function(value) {
                            $scope.closeUsergroupDelteModal();
                            $(document).find(".close").trigger("click");
                        });

                    }, function(response) {

                        $btn.button("reset");
                        var alertMsg = "<div class=\"alert alert-danger\">";
                        window.angular.forEach(response.data, function(value, key) {
                            alertMsg += "<p><i class=\"fa fa-warning\"></i> " + value + ".</p>";
                        });
                        alertMsg += "</div>";
                        form.find(".box-body").before(alertMsg);
                        $(":input[type=\"button\"]").prop("disabled", false);
                        window.swal("Oops!", response.data.errorMsg, "error");
                    });

                });

                $scope.closeUsergroupDelteModal = function () {
                    $uibModalInstance.dismiss("cancel");
                };
            },
            scope: $scope,
            size: "md",
            backdrop  : "static",
            keyboard: true,
        });

        uibModalInstance.result.catch(function () { 
             uibModalInstance.close(); 
        });
    };
}]);