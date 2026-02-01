$("body").on("click", "[data-add]", function(eve) {
    eve.preventDefault();
    var str = $(this).data("add"),
        to = $(this).parent().data("add_to"),
        val = $(to).val();
    $(to).val(val + str);
});