@extends('emails.layouts.app')

@section('content')

    @component('emails.components.content_head')
        @slot('heading')
            Your order shipped!
        @endslot

        @slot('body')
            We would like you to know that your order has shipped! Details below.
        @endslot

        @slot('image')
            <img src="{{ url('assets/images/macbook_iphone_coffee.png') }}" style="max-width:100%; display:block;">
        @endslot
    @endcomponent

    <table cellspacing="0" cellpadding="0" class="force-full-width" bgcolor="#ffffff" >
        <tr>
            <td style="background-color:#ffffff; padding-top: 15px;">

                <center>
                    <table style="margin:0 auto;" cellspacing="0" cellpadding="0" class="force-width-80">
                        <tr>
                            <td style="text-align:left;">
                                <br>
                                <strong>Shipping Address:</strong><br>
                                Bob Erlicious<br>
                                1234 Bobbz Way <br>
                                Victoria, BC <br>
                                V2A 7D8
                            </td>
                            <td style="text-align:right; vertical-align:top;">
                                <br>
                                <b>Order: 23130</b> <br>
                                2014-04-23
                            </td>
                        </tr>
                    </table>

                    <table style="margin:0 auto;" cellspacing="0" cellpadding="0" class="force-width-80">
                        <tr>
                            <td  class="mobile-block">
                                <br>

                                <table cellspacing="0" cellpadding="0" class="force-full-width">
                                    <tr>
                                        <td style="border-bottom:1px solid #e3e3e3; font-weight: bold; text-align:left">
                                            Delivery Date
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="text-align:left;">
                                            Monday, 20th May 2014
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>


                    <table style="margin: 0 auto;" cellspacing="0" cellpadding="0" class="force-width-80">
                        <tr>
                            <td style="text-align: left;">
                                <br>
                                To track or view your order please click the button below. Thank you for your business.<br><br>
                                Awesome Inc
                            </td>
                        </tr>
                    </table>
                </center>

                <table style="margin:0 auto;" cellspacing="0" cellpadding="10" width="100%">
                    <tr>
                        <td style="text-align:center; margin:0 auto;">
                            <br>
                            <div><!--[if mso]>
                                <v:rect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="http://" style="height:45px;v-text-anchor:middle;width:180px;" stroke="f" fillcolor="#f5774e">
                                    <w:anchorlock/>
                                    <center>
                                <![endif]-->
                                <a href="http://"
                                   style="background-color:#f5774e;color:#ffffff;display:inline-block;font-family:'Source Sans Pro', Helvetica, Arial, sans-serif;font-size:18px;font-weight:400;line-height:45px;text-align:center;text-decoration:none;width:180px;-webkit-text-size-adjust:none;">My Order</a>
                                <!--[if mso]>
                                </center>
                                </v:rect>
                                <![endif]--></div>
                            <br>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>
@endsection
