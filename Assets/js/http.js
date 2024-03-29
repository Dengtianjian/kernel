var requestUrl = null;
var view = false;
class CDZXHTTP {
  static setRequestUrl(url) {
    requestUrl = url;
  }
  static view(flag = true) {
    view = flag;
    return CDZXHTTP;
  }
  static send(url, method = "get", params = null) {
    let _view = view;
    return new Promise((resolve, reject) => {
      let config = {
        method,
      };

      if (params instanceof FormData) {
        config["body"] = params;
      } else {
        if (params) {
          config["body"] = JSON.stringify(params);
        }
      }
      let headers = new Headers();
      if (!view) {
        headers.append("x-ajax", "fetch");
      }
      headers.append("x-client", "520");
      config["headers"] = headers;
      if (typeof FORMHASH != "undefined") {
        url += "&formhash=" + FORMHASH;
      }
      fetch(url, config)
        .then((res) => {
          if (res.status === 204) {
            return true;
          }
          if (_view) {
            return res.text();
          }
          return res.json();
        })
        .then((res) => {
          if (res.statusCode > 299) {
            reject(res);
          } else {
            if (typeof res === "object") {
              if (res.data) {
                resolve(res.data);
              }
            }
            resolve(res);
          }
        }).catch(err => {
          showError(err.message ?? "加载失败，请稍后重试");
        }).finally(() => {
          view = false;
        })
    });
  }
  static makeQueryString(queryObj) {
    let queryString = [];
    for (const key in queryObj) {
      queryString.push(`${key}=${queryObj[key]}`);
    }
    queryString = queryString.join("&");
    return queryString;
  }
  static get(uri, query = null) {
    let url = requestUrl;
    url += "&uri=" + uri;
    if (query) {
      url += "&" + this.makeQueryString(query);
    }
    return this.send(url, "get");
  }
  static post(uri, params = null) {
    let url = requestUrl + "&uri=" + uri;
    return this.send(url, "post", params);
  }
  static delete(uri, params = null) {
    let url = requestUrl + "&uri=" + uri;
    return this.send(url, "delete", params);
  }
  static patch(uri, params = null) {
    let url = requestUrl + "&uri=" + uri;
    return this.send(url, "put", params);
  }
  static put(uri, params = null) {
    let url = requestUrl + "&uri=" + uri;
    return this.send(url, "put", params);
  }
  static upload(uri, files) {
    let url = requestUrl + "&uri=" + uri;
    let fileForm = new FormData();
    if (files instanceof File) {
      fileForm.append("file", files);
    } else if (Array.isArray(files) || files instanceof FileList) {
      if (files.length === 0) {
        return null;
      }

      if (files instanceof FileList) {
        let filesTemp = [];
        for (let index = 0; index < files.length; index++) {
          filesTemp.push(files[index]);
        }
        files = filesTemp;
      }

      files.forEach((item, index) => {
        fileForm.append("file" + index, item);
      });
    } else {
      return Promise.reject(400);
    }
    return this.send(url, "post", fileForm);
  }
}
