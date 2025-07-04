<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewDepositNotification extends Notification
{
    use Queueable;

    /*** @var $name
     */
    public $name;

    /*** @var $amout
     */
    public $amout;

    /*** Create a new notification instance.
     */
    public function __construct($name, $amout)
    {
        $this->name  = $name;
        $this->amout = $amout;
    }

    /*** Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /*** Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->view(
            'emails.new-deposit', ['usuario' => $this->name, 'valor' => \Helper::amountFormatDecimal($this->amout)]
        );
    }

    /*** Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'Olá Administrador, Informamos que um novo depósito no valor de ' . \Helper::amountFormatDecimal($this->amout) . ' , realizado pelo usuário' . $this->name,
        ];
    }
}
