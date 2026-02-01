var dice = document.getElementById("dice");

document.addEventListener("mousemove", function(event) {
    var x = event.clientX;
    var y = event.clientY;
    dice.style.top = y + "px";
    dice.style.left = x + "px";
});

dice.addEventListener("click", function() {
    var randomNumber = Math.floor(Math.random() * 6) + 1;
    dice.innerHTML = randomNumber;
    dice.classList.add("rolling");
    setTimeout(function() {
        dice.classList.remove("rolling");
    }, 2000);
});