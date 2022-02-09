function uploadImagePreview(file,selectors){
  const fr=new FileReader();
  fr.onload=function(e){
    document.querySelector(selectors).setAttribute("src",fr.result);
  }
  fr.readAsDataURL(file);
}