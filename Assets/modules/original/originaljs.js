function Query(selectors, rooEl = null) {
  if (rooEl) {
    return rooEl.querySelector(selectors);
  }
  return document.querySelector(selectors);
}
class Transition {
  constructor() {
    this.$el = null;
    this.transitions = [];
    this.RAFId = null;
    this.startTimestamp = null;
    this.endCallBack = null;
    this.insertedPropertyNames = new Set();
    this.isCleanStyle = false;
  }
  updateStyle(transitionItem) {
    const PropertyNames = Object.keys(transitionItem.propertys);
    this.$el.style.transitionProperty = PropertyNames.join(",");
    this.$el.style.transitionDuration = `${transitionItem.duration}s`;
    this.$el.style.transitionTimingFunction = transitionItem.timingFunction;
    for (const propertyName in transitionItem.propertys) {
      if (Object.prototype.hasOwnProperty.call(transitionItem.propertys, propertyName)) {
        const transition = transitionItem.propertys[propertyName];
        this.$el.style[propertyName] = transition;
        this.insertedPropertyNames.add(propertyName);
      }
    }
  }
  trigger() {
    if (this.startTimestamp === null) {
      this.startTimestamp = Date.now();
    }
    const elapsed = Date.now() - this.startTimestamp;
    const transition = this.transitions[0];
    this.updateStyle(transition);
    if (elapsed < transition.duration * 1e3) {
      this.RAFId = window.requestAnimationFrame(this.trigger.bind(this));
    } else {
      this.transitions.shift();
      this.startTimestamp = null;
      if (transition.callback) {
        transition.callback();
      }
      if (this.isCleanStyle) {
        for (const propertyName of this.insertedPropertyNames.keys()) {
          this.$el.style[propertyName] = "";
        }
        this.insertedPropertyNames.clear();
        this.isCleanStyle = false;
      }
      window.cancelAnimationFrame(this.RAFId);
      this.RAFId = window.requestAnimationFrame(this.trigger.bind(this));
      if (this.transitions.length === 0) {
        window.cancelAnimationFrame(this.RAFId);
        this.$el.style.transitionProperty = "";
        this.$el.style.transitionDuration = "";
        this.$el.style.transitionTimingFunction = "";
        if (this.endCallBack) {
          this.endCallBack();
        }
      }
    }
  }
  el(selector, rootEl = null) {
    if (this.$el === null) {
      this.$el = Query(selector, rootEl);
    }
    return this;
  }
  step(propertys, duration = 0.3, timingFunction = "linear", callback) {
    this.transitions.push({
      propertys,
      duration,
      timingFunction,
      callback
    });
    this.trigger();
    return this;
  }
  end(callback) {
    this.endCallBack = callback;
    return this;
  }
  clear() {
    this.isCleanStyle = true;
    return this;
  }
}
class Element extends HTMLElement {
  constructor() {
    super();
    this._customElement = true;
    this.$ref = null;
    this._state = {};
    this._methods = {};
    this._props = {};
    this.$ref = this.attachShadow({ mode: "closed" });
  }
  connectedCallback() {
    this._render();
    this.connected();
  }
  disconnectedCallback() {
    this.disconnected();
  }
  adoptedCallback() {
    this.adoptied();
  }
  connected() {
  }
  disconnected() {
  }
  adoptied() {
  }
  propChanged(name, newV, oldV) {
  }
  attributeChangedCallback(name, oldV, newV) {
    this.propChanged(name, newV, oldV);
    this.setState(name, newV);
  }
  render() {
    return null;
  }
  _render() {
    const template = this.render();
    let appendNodes = [];
    if (typeof template === "string") {
      const document2 = new DOMParser().parseFromString(template, "text/html");
      const headChildNodes = document2.childNodes[0].childNodes[0].childNodes;
      const bodyChildNodes = document2.childNodes[0].childNodes[1].childNodes;
      appendNodes.push(...Array.from(headChildNodes), ...Array.from(bodyChildNodes));
    } else {
      if (template instanceof NodeList) {
        appendNodes = appendNodes;
      } else {
        appendNodes = [template];
      }
    }
    for (const nodeItem of appendNodes) {
      this._reactive(nodeItem);
      this._bindMethods(nodeItem);
    }
    this.$ref.append(...appendNodes);
  }
  _reactive(El) {
    if (El.childNodes.length > 0) {
      El.childNodes.forEach((node) => {
        this._reactive(node);
      });
    }
    if (El.attributes) {
      const attributes = Array.from(El.attributes);
      for (let index2 = 0; index2 < attributes.length; index2++) {
        const attrItem = attributes[index2];
        const vars2 = attrItem.nodeValue.match(/(?<=\{).+?(?=\})/g);
        if (vars2 === null) {
          continue;
        }
        let replaceContent = attrItem.nodeValue;
        vars2.forEach((varItem) => {
          if (this[varItem] !== void 0) {
            let replace = this[varItem].toString();
            replaceContent = replaceContent.replace(`{${varItem}}`, replace);
          }
          if (!this._state[varItem]) {
            this._state[varItem] = {
              value: this[varItem],
              els: new Set()
            };
          }
          this._state[varItem].els.add({
            attribute: attrItem,
            type: "attribute"
          });
        });
        attrItem.nodeValue = replaceContent;
      }
    }
    if (El.nodeType !== 3) {
      return true;
    }
    let ElHTML = "";
    switch (El.nodeType) {
      case 3:
        ElHTML = El.textContent;
        break;
      default:
        ElHTML = El.innerHTML;
        break;
    }
    const vars = ElHTML.match(/(?<=\{).+?(?=\})/g);
    if (vars === null) {
      return true;
    }
    vars.forEach((varItem) => {
      let replace;
      if (this[varItem]) {
        replace = this[varItem].toString();
        ElHTML = ElHTML.replace(`{${varItem}}`, replace);
      } else {
        console.warn(`
        CM:\u5B58\u5728\u672A\u5B9A\u4E49\u7684\u54CD\u5E94\u5F0F\u53D8\u91CF:${varItem}\u3002
        EN:undefined reactive variables:${varItem}.
        `);
      }
      if (!this._state[varItem]) {
        this._state[varItem] = {
          value: this[varItem] || `{${varItem}}`,
          els: new Set()
        };
      }
      this._state[varItem].els.add({
        el: El,
        type: "element"
      });
    });
    switch (El.nodeType) {
      case 3:
        El.textContent = ElHTML;
        break;
      default:
        El.innerHTML = ElHTML;
        break;
    }
    return true;
  }
  _bindMethods(El) {
    if (El.childNodes.length > 0) {
      El.childNodes.forEach((node) => {
        this._bindMethods(node);
      });
    }
    if (El.attributes && El.attributes.length > 0) {
      Array.from(El.attributes).forEach((attrItem) => {
        if (/^on[a-z]+$/.test(attrItem.name) && /^\w+(\(\))?/.test(attrItem.value)) {
          const methodName = String(attrItem.value).match(/\w+(\(.+\))?;?/g);
          El[attrItem["localName"]] = null;
          for (const name of methodName) {
            const params = this._parserParams(name);
            const methodNameItem = name.match(/\w+(?=\(.+\))?/);
            if (methodNameItem === null) {
              continue;
            }
            const listener = this[methodNameItem[0]].bind(this, ...params);
            let type = attrItem.localName.match(/(?<=on)\w+/g);
            if (type === null) {
              continue;
            }
            if (!this._methods[methodNameItem[0]]) {
              this._methods[methodNameItem[0]] = [];
            }
            this._methods[methodNameItem[0]].push({
              el: El,
              type: type[0],
              listener,
              params
            });
            El.addEventListener(type[0], listener);
          }
          El.removeAttribute(attrItem["localName"]);
        }
      });
    }
    return true;
  }
  _parserParams(paramsString) {
    const paramsRaw = String(paramsString).match(/(?<=\().+(?=\))/);
    if (paramsRaw === null) {
      return [];
    }
    let params = [];
    if (paramsRaw !== null) {
      params = paramsRaw[0].split(",");
    }
    return params;
  }
  async setState(key, value) {
    const state = this._state[key];
    if (typeof value === "function") {
      if (value.constructor.name === "AsyncFunction") {
        value = await value();
      } else {
        value = value();
      }
    }
    //! 与_reactive重复代码 需优化
    if (state && state.value.toString() !== value.toString()) {
      for (let index2 = 0; index2 < Array.from(state.els).length; index2++) {
        const elItem = Array.from(state.els)[index2];
        if (elItem.type === "attribute") {
          if (/^on\w+/.test(elItem.attribute.nodeName)) {
            if (elItem.attribute.value.includes(this[key]) && /^\w+(\(\))?/.test(elItem.attribute.value)) {
              const methodName = String(elItem.attribute.value).match(/\w+(\(.+\))?;?/g);
              if (methodName === null) {
                continue;
              }
              for (let nameItem of methodName) {
                nameItem = nameItem.replaceAll(state.value.toString(), value.toString());
                const params = this._parserParams(nameItem);
                const methodNameItem = nameItem.match(/\w+(?=\(.+\))?/);
                if (methodNameItem === null) {
                  continue;
                }
                this.setMethod(methodNameItem[0], this[methodNameItem[0]], params);
              }
            }
          } else {
            elItem.attribute.nodeValue = elItem.attribute.nodeValue.replaceAll(state.value.toString(), value.toString());
          }
        } else {
          if (elItem.el.nodeType === 3) {
            elItem.el.textContent = elItem.el.textContent.replace(state.value.toString(), value.toString());
          } else {
            elItem.el.innerHTML = elItem.el.innerHTML.replaceAll(state.value.toString(), value.toString());
          }
        }
      }
      state.value = value;
    }
    if (this._props.includes(key) && value.toString() !== this.getAttribute(key)) {
      this.setAttribute(key, value.toString());
    }
    if (value !== this[key]) {
      this[key] = value;
    }
  }
  async setMethod(name, func, params = []) {
    const els = this._methods[name];
    els.forEach((elItem) => {
      const listener = func.bind(this, ...params);
      elItem.el.removeEventListener(elItem.type, elItem.listener);
      elItem.el.addEventListener(elItem.type, listener);
      elItem.listener = listener;
      elItem.params = params;
    });
  }
}
Element.observedAttributes = [];
function defineElement(name, constructor, options = {}) {
  window.customElements.define(name, constructor, options);
}
function createElement(props = []) {
  var _a;
  return _a = class extends Element {
    constructor() {
      super();
      this._props = props;
    }
  }, _a.observedAttributes = props, _a;
}
function importTemplate(filePath) {
  return "";
}
function ObserverNode(target, callback, options = {
  attributes: true
}) {
  const mt = new MutationObserver((mutations, observer) => {
    callback(mutations, observer);
  });
  mt.observe(target, options);
  return mt;
}
function ObserverNodeAttributes(target, attributeFilter, calback) {
  attributeFilter = typeof attributeFilter === "string" ? [attributeFilter] : attributeFilter;
  return ObserverNode(target, calback, {
    attributeFilter
  });
}
var index = {
  Transition,
  Element,
  ObserverNode,
  ObserverNodeAttributes,
  defineElement,
  createElement,
  importTemplate,
  Query
};
export default index;
export { Element, ObserverNode, ObserverNodeAttributes, Query, Transition, createElement, defineElement, importTemplate };
