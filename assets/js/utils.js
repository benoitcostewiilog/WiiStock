export function deepCopy(object) {
    return object !== undefined ? JSON.parse(JSON.stringify(object)) : object;
}

export function mobileCheck() {
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)
        || window.screen.width <= 992;
}

export function capitalize(string) {
    if (typeof string !== `string`) return ``;
    return string.charAt(0).toUpperCase() + string.slice(1);
}
