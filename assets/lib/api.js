export function authorizationHeaders(token) {
    return {
        Authorization: `Bearer ${token}`,
    };
}
