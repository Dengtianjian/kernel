const MessageEls = {};
let MessageLastKey = null;

function CMessage(message, hiddenTimeout = 3000) {
  let positionTop = "5vh";
  const currentKey = "Message_" + Date.now();
  let lastEl = null;
  if (MessageLastKey) {
    lastEl = MessageEls[MessageLastKey];
    if (lastEl) {
      positionTop = lastEl.offsetTop + lastEl.clientHeight + 20 + "px";
    } else {
      MessageLastKey = null;
    }
  }
  const El = document.createElement("div");
  El.className = "c-message";
  El.style.top = positionTop;
  const IconEl = document.createElement("i");
  IconEl.className = "shoutao st-icon-infofill";
  El.appendChild(IconEl);
  const MessageEl = document.createTextNode(message);
  El.appendChild(MessageEl);
  document.body.appendChild(El);
  MessageEls[currentKey] = El;
  MessageLastKey = currentKey;
  setTimeout(() => {
    El.style.animation = "CFadeOut 0.3s";
    El.onanimationend = function () {
      El.style.display = "none";
      document.body.removeChild(El);
      delete MessageEls[currentKey];
    };
  }, hiddenTimeout);
}
