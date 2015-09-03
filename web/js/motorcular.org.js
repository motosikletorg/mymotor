/**
 * Created by deniz on 31/08/15.
 */

brandTree = {};
brandModel = function (modelId, model2Id, modelName, years) {

    this.modelId = modelId;
    this.modelName = modelName;
    this.years = years;
    this.model2Id = model2Id;


}
brandData = function (brandId, brandName, models) {

    this.brandId = brandId;
    this.brandName = brandName;
    this.models = models;
    brandTree[brandId] = this;

    this.addModel = function (modelId, model2Id, modelName, years) {

        this.models[modelId] = new brandModel(modelId, model2Id, modelName, years);
    }


}
brandCrawler = {
    $proxy: null,
    $brandBoxFrom: [],
    $brandBoxTo: [],
    $brands: [],
    completed:false,
    init: function ($crawlerPort) {
        brandCrawler.$proxy = $crawlerPort;

        $.get(this.$proxy + '?url=http://www.motorcular.com/tr/motosiklet-karsilastirma', function (data) {
            $page = $(data);
            var brand1 = $("#marka1 option", $page);
            $.map(brand1, function (option) {
                brandCrawler.$brandBoxFrom.push([option.text, option.value]);
            });
            var brand2 = $("#marka2 option", $page);
            $.map(brand2, function (option) {
                brandCrawler.$brandBoxTo.push([option.text, option.value]);
            });
            brandCrawler.initBrands();
        });
    },

    initBrands: function () {
        $.each(brandCrawler.$brandBoxFrom, function (itemIndex, content) {
            if (content[1] != "") {
                brandCrawler.$brands.push(content);
                $.get(brandCrawler.$proxy + '?url=http://www.motorcular.com/tr/ajax_servis/marka&method=post&data={"marka":' + content[1] + '}', function (data) {
                    bdata = new brandData(content[1], content[0], {});
                    brandCrawler.initModels(bdata, $.parseJSON(data).data);
                });
            }
        })
    },
    initModels: function (bdata, models) {
        $.each(models, function (itemAt, item) {
            $.get(brandCrawler.$proxy + '?z=t&url=http://www.motorcular.com/tr/ajax_servis/model&method=post&data={"model":' + item.ilan_kategori_id + '}', function (data) {
                bdata.addModel(item.ilan_kategori_ad_id,  item.ilan_kategori_id, item.ilan_kategori_ad,data)
            });
        })
    },
    report:function()
    {
        $.post('/app_dev.php/finalize',{"content":JSON.stringify(brandTree)} );
    }
};