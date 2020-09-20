<?php

namespace App\Form;

use App\Entity\Card;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class CardFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // https://symfony.com/doc/current/form/form_customization.html
        $choices = array("北海道" => "北海道", "青森県" => "青森県", "岩手県" => "岩手県", "宮城県" => "宮城県", "秋田県" => "秋田県", "山形県" => "山形県", "福島県" => "福島県", "茨城県" => "茨城県", "栃木県" => "栃木県", "群馬県" => "群馬県", "埼玉県" => "埼玉県", "千葉県" => "千葉県", "東京都" => "東京都", "神奈川県" => "神奈川県", "新潟県" => "新潟県", "富山県" => "富山県", "石川県" => "石川県", "福井県" => "福井県", "山梨県" => "山梨県", "長野県" => "長野県", "岐阜県" => "岐阜県", "静岡県" => "静岡県", "愛知県" => "愛知県", "三重県" => "三重県", "滋賀県" => "滋賀県", "京都府" => "京都府", "大阪府" => "大阪府", "兵庫県" => "兵庫県", "奈良県" => "奈良県", "和歌山県" => "和歌山県", "鳥取県" => "鳥取県", "島根県" => "島根県", "岡山県" => "岡山県", "広島県" => "広島県", "山口県" => "山口県", "徳島県" => "徳島県", "香川県" => "香川県", "愛媛県" => "愛媛県", "高知県" => "高知県", "福岡県" => "福岡県", "佐賀県" => "佐賀県", "長崎県" => "長崎県", "熊本県" => "熊本県", "大分県" => "大分県", "宮崎県" => "宮崎県", "鹿児島県" => "鹿児島県", "沖縄県" => "沖縄県");
        $builder
            ->add('name_sei', null, array('label' => 'お名前(姓)', 'help' => 'あなたのお名前(姓)を入力してください。', 'attr' => array('class' => '', 'placeholder' => '例) 山田')))
            ->add('name_mei', null, array('label' => 'お名前(名)', 'help' => 'あなたのお名前(名)を入力してください。', 'attr' => array('placeholder' => '例) 花子')))
            ->add('email', HiddenType::class, array('label' => 'メールアドレス'))
            ->add('zip', null, array('label' => '郵便番号', 'help' => 'あなたの郵便番号を入力してください。', 'attr' => array('placeholder' => '例) 123-0001')))
            ->add('pref', ChoiceType::class, array('label' => '都道府県', 'choices' => $choices, 'help' => 'あなたのお住いの都道府県を入力してください。', 'attr' => array('placeholder' => '例) 石川県')))
            ->add('addr1', null, array('label' => '市町村', 'help' => 'あなたのお住いの市町村を入力してください。', 'attr' => array('placeholder' => '例) 金沢市')))
            ->add('addr2', null, array('label' => '町名', 'help' => 'あなたのお住いの町名を入力してください。', 'attr' => array('placeholder' => '例) 広坂1-1')))
            ->add('addr3', null, array('label' => '部屋番号など', 'help' => 'あなたのお住いのマンションなどの部屋番号を入力してください。', 'attr' => array('placeholder' => '例) メゾン金沢101')))
            ->add('tel', null, array('label' => '電話番号', 'help' => 'あなたの電話番号を入力してください。', 'attr' => array('placeholder' => '例) 090-0000-0000')))
        ;
    }
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Card::class,
        ]);
    }
}
