<?php if ($application['is_draft']) { ?>
        <div class="greeting-content">
            <h2 class="greeting-title">Quick Sigorta ailesine katılmak için gösterdiğiniz ilgiye çok teşekkür ederiz.</h2>
            <p>Başvurunuzu etkin bir şekilde değerlendirebilmek için sizlerden alttaki formu doldurmanızı rica ediyoruz.</p>
            <p>Bize sağlayacağınız bilgiler ışığında yapılacak olan değerlendirme sonucunu sizlerle paylaşacağız. Olası gecikmeler için sizlerden sabır ve anlayış rica ediyoruz. Tekrar bize gösterdiğiniz ilgi için teşekkürler.</p>
        </div>
        <?php } else { ?>
        <div class="greeting-content">
            <h2 class="greeting-title">Başvurduğunuz için teşekkür ederiz.</h2>
            <p>Başvurunuz incelenmektedir. Olası gecikmeler için sizlerden sabır ve anlayış rica ediyoruz.
            <p>Bilgilerinizde değişiklik oldu ise başvurunuzu düzenleyebilirsiniz.</p>
            <p>Tekrar bize gösterdiğiniz ilgi için teşekkürler.</p>
        </div>
        <?php } ?>