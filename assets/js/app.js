// Shared JavaScript helpers. Owner: Zan
// callApi() sends a request to our PHP API and returns the JSON answer.
// Example: const res = await callApi('GET', '../api/menus.php');
async function callApi(method, url, body = null) {
  const options = { method: method, headers: { 'Content-Type': 'application/json' } };
  if (body) {
    options.body = JSON.stringify(body);   // turn a JS object into JSON text
  }
  const response = await fetch(url, options); // send the request
  return await response.json();              // { success, data, error }
}
