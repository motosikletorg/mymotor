/**
 * Created by deniz on 31/08/15.
 */$

brandCrawler = {
    $proxy: null,
    $brandBoxFrom: [],
    $brandBoxTo: [],

    init: function ($crawlerPort) {
        this.$proxy = $crawlerPort;

        $.get(this.$proxy + '?url=http://www.motorcular.com/tr/motosiklet-karsilastirma', function (data) {
            $page = $(data);
            var brand1 = $("#marka1 option", $page);
            var brand2 = $("#marka2 option", $page);

            $.map(brand1, function (option) {
                this.$brandBoxFrom.push([option.text, option.value]);

            });
            $.map(brand2, function (option) {
                this.$brandBoxFrom.push([option.text, option.value]);

            });

            console.log("brand1", this.$brandBoxFrom);
            console.log("brand2", this.$brandBoxTo);

            $.get(this.$proxy + '?url=http://www.motorcular.com/tr/ajax_servis/marka&method=post&data={"marka":13}', function (data) {
                console.log(data);
            });

        });
    },


};