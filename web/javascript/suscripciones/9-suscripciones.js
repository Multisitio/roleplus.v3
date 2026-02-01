$(() => {
    paypal.Buttons({
        style: {
            shape: 'rect',
            color: 'white',
            layout: 'horizontal',
            label: 'subscribe'
        },
        createSubscription: function(data, actions) {
            return actions.subscription.create({
                'plan_id': 'P-8G107520PX205843HL5UF5YQ'
            });
        },
        onApprove: function(data, actions) {
            alert(data.subscriptionID);
        }
    }).render('.plan-5');

    paypal.Buttons({
        style: {
            shape: 'rect',
            color: 'silver',
            layout: 'horizontal',
            label: 'subscribe'
        },
        createSubscription: function(data, actions) {
            return actions.subscription.create({
                'plan_id': 'P-5AR945310N780463VL5UF67Q'
            });
        },
        onApprove: function(data, actions) {
            alert(data.subscriptionID);
        }
    }).render('.plan-10');

    paypal.Buttons({
        style: {
            shape: 'rect',
            color: 'gold',
            layout: 'horizontal',
            label: 'subscribe'
        },
        createSubscription: function(data, actions) {
            return actions.subscription.create({
                'plan_id': 'P-6V313669A0922131YL5UF7ZY'
            });
        },
        onApprove: function(data, actions) {
            alert(data.subscriptionID);
        }
    }).render('.plan-20');

    paypal.Buttons({
        style: {
            shape: 'rect',
            color: 'black',
            layout: 'horizontal',
            label: 'subscribe'
        },
        createSubscription: function(data, actions) {
            return actions.subscription.create({
                'plan_id': 'P-3XC975697F6156408L5UGA2Q'
            });
        },
        onApprove: function(data, actions) {
            alert(data.subscriptionID);
        }
    }).render('.plan-40');
});