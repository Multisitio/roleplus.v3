function storage(action, key, val) {
    if (action == 'save') localStorage.setItem(key, val);
    else if (action == 'read') return localStorage.getItem(key);
    else if (action == 'quit') localStorage.removeItem(key);
    else if (action == '+') localStorage.setItem(key, Number(localStorage.getItem(key)) + Number(val));
    else if (action == '-') localStorage.setItem(key, Number(localStorage.getItem(key)) - Number(val));
    else if (action == 'clear') localStorage.clear();
    return localStorage.getItem(key);
}