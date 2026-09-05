Kumbia.utils.on("click", "[data-add]", function(eve) {
    eve.preventDefault();
    var str = Kumbia.utils.getData(this, "add"),
        toSel = this.parentElement.getAttribute("data-add_to"),
        toEl = document.querySelector(toSel);
    if(toEl) toEl.value = (toEl.value || "") + str;
});