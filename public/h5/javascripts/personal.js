$(function(){
	$(".guanYu").click(function(){
		window.location.href='about.html';
	});
	$(".tiBi").click(function(){
		window.location.href='bindAddress.html'; 
	});
	$(".shouKuan").click(function(){
		window.location.href='adding.html'; 
	});
	$(".anQuan").click(function(){
		window.location.href='Security.html'; 
	});
	$(".geRen").click(function(){
		window.location.href='personage.html'; 
	});
	$(".yaoQing").click(function(){
		window.location.href= "invite.html"; 
	});
	$(".dingDan").click(function(){
		window.location.href='Entrust.html'; 
	});
	$(".jiTou").click(function(){
		window.location.href='assets.html'; 
	});
	$(".legal").click(function(){
		window.location.href='fiatrad_detail.html';
	});
    $(".seller").click(function(){
        window.location.href='legal_operation.html';
	});
	$(".trade").click(function(){
        window.location.href='trade.html';
    });
	// 我的商铺
	$('.ft_shop').click(function(){
		location.href='shop_fiatrad.html'
	})
	$('.fabu').click(function(){
		location.href='../c2c/fiatrad_fabu.html'
	})
	$('.fabu_detail').click(function(){
		location.href='../c2c/fiatrad_detail.html'
	})
	$('.transfer').click(function(){
		location.href='transfer_money.html'
	})
	$('.share').click(function(){
		location.href='sharecode.html?code='+$(this).data('id')
	})
	$('.lever').click(function(){
		location.href='leverList.html';
	})
	$('.sweet').click(function(){
		location.href='sweet.html';
	})
	$('.huiyuan').click(function(){
		location.href='tree.html';
	})
	$('.reward').click(function(){
		location.href='reward_record.html';
	})
	$('.profit').click(function(){
		location.href='profit.html';
	})
	$('.c2c').click(function(){
		location.href='../c2c/fiatrad.html';
	})
})