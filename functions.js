function sfmShowHide(idElement){
	folderContent = 'into_' + idElement;
	el = document.getElementById(folderContent);
	if(el.style.display == "block"){
		el.style.display = "none";
	} else {
		el.style.display = "block";
	}
	sfmOpenClose(idElement);
}