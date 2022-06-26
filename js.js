
function onSubmitAdd(){
    //this.preventDefault();
    //examine input validity, completeness using RE, etc here -> more computing here less load on server side
     for (let i = 0; i < arguments.length; i++){
         if (document.getElementsByName(arguments[i])){
            document.getElementsByName(arguments[i])[0].value += '$';
         }
    }
    return false; //still added even though it returns false
}

function onSubmitLogin(){
    //alert('..onLogin..');
}