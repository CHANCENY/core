(function($doc){
    $doc.addEventListener('DOMContentLoaded', ()=>{
        const message_fetch = async ()=> {
            const response = await fetch('/system/messages');
            const result = await response.json();
            if (result.length > 0) {
                result.forEach((message)=>{
                    setTimeout(()=>{
                        Toastify({
                            text: message.message,
                            duration: message.time,
                            close: true,
                            gravity: "top",
                            position: "right",
                            stopOnFocus: true,
                            className: message.type,
                            style: {
                                background: "linear-gradient(to right, #00b09b, #96c93d)",
                            },
                        }).showToast();
                    }, 3000);
                })
            }
        }
        message_fetch();
    })
})(document);