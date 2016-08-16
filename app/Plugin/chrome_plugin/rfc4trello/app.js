var mainUpdateInterval;
var mainUpdateStep = 5000;
var base_url = "https://localhost/rfc4trello/";
//RFCデータを取得する
$( document ).ready(function() {
    setRfcInfo();
    //繰り返し
    mainUpdateInterval = setInterval(setRfcInfo, mainUpdateStep);
    
});

function stopUpdate() {
    window.clearInterval(mainUpdateInterval);
}


function setRfcInfo() {
    var sURLVariables = location.pathname.split("/");
    var boardId = sURLVariables[2];

    $.ajax({
        url: base_url + "getJson/" + boardId,
        data: "",
        type: "GET",
        async: true,
        success: function(data) {
            //$("#" + count).html(data);
            console.log(data);
            console.log(data.boardId);
            average_coef = 1.0;
            if (data.average_coef) {
                average_coef = data.average_coef;
            }
            if($("#rfcCoeficientNo").length === 0) {
                $("#header > div.header-user > a.header-btn.js-open-add-menu")
                    .before('<a class="header-btn" href="#" id="rfcCoeficientNo"><span class="header-btn-text">RFC係数：' 
                    + average_coef +'</span></a>')
                    .before('<a class="header-btn"  id="exportRfcToExcel" href="' + base_url + 'export/' + data.boardId +'">\n\
                        <span class="header-btn-text">Excelに出力</span></a>');

            } else {
                $("#rfcCoeficientNo").remove();
                $("#exportRfcToExcel").remove();
                $("#header > div.header-user > a.header-btn.js-open-add-menu")
                    .before('<a class="header-btn"  id="rfcCoeficientNo" href="#"><span class="header-btn-text">RFC係数：' 
                    + average_coef +'</span></a>')
                    .before('<a class="header-btn" id="exportRfcToExcel" href="'+ base_url + 'export/' + data.boardId +'"><span class="header-btn-text">Excelに出力</span></a>');
            }
                        
            //カードにデータを追加する
            sprint_element = $("h2:contains('This_Sprint_Backlog')").first();            
            console.log(sprint_element);
            title_contents = "";
            if ((typeof sprint_element !== 'undefined') && (typeof sprint_element.html() !== 'undefined')) {
                title_contents = sprint_element.html().split(":");
            }
            //title_contents = sprint_element.html().split(":");
            end_date = "";
            if (title_contents.length > 1) {
                end_date = title_contents[1];
            }
            console.log(end_date);
            console.log(data.rfc_card);
            if (typeof data.rfc_card === 'undefined') {
                return;
            }
            
            
            $.each(data.rfc_card, function() {
                //すでに完了したチケット、スキップする
                if (this.Done.date.length > 0) {
                    return true; //次に繰り返し
                }
                
                if (this.RoughDoing.startDate.length === 0) {
                    return true; //次に繰り返し
                }
                
                span_html = "span:contains('#"+ this.Card.idShort +" ')";
                links = $(span_html).parents("a");
                console.log("parent a tag with the same hidden id" + links.length);
                endDateObj = new Date(end_date);
                console.log($(span_html));
                sprint_id = "#sprintEndDate" + this.Card.idShort;
                console.log($(sprint_id));
                link = $(span_html).last().parent("a");
                if($(sprint_id).length > 0) {                    
                     $(sprint_id).remove();                    
                }
                link.after(' <div title="スプリントの完了日" class="badge-state-due-past badge"  style="background-color: blue;" id="sprintEndDate' 
                        + this.Card.idShort +'">\n\
                        <span class="badge-icon icon-sm icon-clock"></span> \n\
                        <span class="badge-text">' + (endDateObj.getMonth() + 1) + '/' + endDateObj.getDate() +'</span> </div>');                        
                
                
                console.log(this.RoughDoing.startDate);
                console.log(this.RoughDoing.due > 0);
                console.log(this.Done.date);
                if (this.RoughDoing.due > 0) {
                    var startDate = new Date(this.RoughDoing.startDate);
                    startDate.setHours(0,0,0,0);
                    var endPlanDate = new Date(end_date);
                    var businessDays = average_coef * this.RoughDoing.due;
                    console.log(businessDays);
                    var counter = 0; // set to 1 to count from next business day
                    while( businessDays > 0 ){
                    var toDate = new Date(startDate); 
                    toDate.setDate(startDate.getDate() + counter++);
                    switch(toDate.getDay()){
                        case 0: case 6: break;// sunday & saturday
                        default:
                            businessDays--;
                        }; 
                    }                    
                    //link = $(span_html).parents("a");
                    console.log(link.hasClass( "agile_hidden"));
                    console.log("startDate=" + startDate);
                    console.log("endPlanDate=" + endPlanDate);
                    console.log("toDate=" + toDate);
                    taskId = "#taskEndPlanDate" + this.Card.idShort;
                    console.log($(taskId));
                    link = $(span_html).last().parents("a");
                    if($(taskId).length > 0) {     
                        $(taskId).remove();
                    }                  
                       
                    link.after(' <div title="タスクの完了予定日" class="badge-state-due-past badge" style="background-color: crimson;" id="taskEndPlanDate'
                        + this.Card.idShort +'">\n\
                        <span class="badge-icon icon-sm icon-clock"></span> \n\
                        <span class="badge-text">' + (toDate.getMonth() + 1) + '/' + toDate.getDate() +'</span> </div>');    

                    if ((toDate.getTime() > endPlanDate.getTime())) {
                        console.log( $(span_html).parent("a").parent("div.list-card-details.clearfix"));
                        $(span_html).parent("a").parent("div.list-card-details.clearfix").css("background-color", "lightcoral");
                    } else {
                        $(span_html).parent("a").parent("div.list-card-details.clearfix").css("background-color", "white");
                    }
                }        
                               
            });
        },
        error: function(e) {
            console.log('Error - ' + e.statusText);            
        }
    });
}