<?php

namespace App\Console\Commands;

use App\Infrastructure\History\Persistence\Mongo\Models\HistoricalContextModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ImportJohnHistoricalContext extends Command
{
    protected $signature = 'history:import-john
        {--language=en : Language code to store}
        {--bible-version=cpdv : Bible version key to store}
        {--fresh : Delete existing John history for this language/version before importing}';

    protected $description = 'Import chapter-level historical background and setting data for the Gospel of John.';

    public function handle(): int
    {
        $language = strtolower((string) $this->option('language'));
        $version = strtolower((string) $this->option('bible-version'));
        $contexts = $this->contexts($language, $version);

        if ($this->option('fresh')) {
            $deleted = HistoricalContextModel::where('book', 'john')
                ->where('language', $language)
                ->where('version', $version)
                ->delete();

            $this->info("Deleted $deleted existing John historical contexts.");
        }

        foreach ($contexts as $context) {
            HistoricalContextModel::updateOrCreate(
                [
                    'book' => $context['book'],
                    'chapter' => $context['chapter'],
                    'verse' => $context['verse'],
                    'language' => $context['language'],
                    'version' => $context['version'],
                ],
                [
                    'summary' => $context['summary'],
                    'details' => $context['details'],
                    'references' => [],
                    'sources' => $context['sources'],
                ]
            );
        }

        Cache::flush();
        $this->info('Imported '.count($contexts)." John historical contexts as language=$language version=$version.");

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function contexts(string $language, string $version): array
    {
        return [
            $this->john(1, $language, $version, 'John opens with a prologue about the eternal Word, then introduces John the Baptist and the first disciples in Judea, beyond the Jordan, and Galilee.', 'The chapter uses creation language, Jewish wisdom themes, and formal witness testimony to frame the Gospel. Its early geography moves from Jerusalem authorities questioning John to the Baptist\'s witness and the gathering of disciples who identify Jesus with Israel\'s hopes.', ['John 1:1-51', 'Genesis 1:1', 'Isaiah 40:3']),
            $this->john(2, $language, $version, 'The narrative moves from a village wedding at Cana to Passover in Jerusalem, pairing a Galilean sign with a public temple confrontation.', 'Cana introduces John\'s pattern of signs that reveal Jesus\' glory. The temple scene places Jesus in Jerusalem during pilgrimage festival traffic, where commerce, sacrifice, and authority converge, and it foreshadows conflict over where God\'s presence is truly encountered.', ['John 2:1-25', 'Psalm 69:9', 'Passover pilgrimage']),
            $this->john(3, $language, $version, 'Jesus speaks at night with Nicodemus, a Pharisee and ruler, then the chapter turns to baptismal activity and John the Baptist\'s final witness.', 'The background includes Jewish teacher-disciple discourse, purification concerns, and the wilderness memory of Moses lifting the serpent. The chapter contrasts earthly and heavenly testimony while showing early overlap between Jesus\' ministry and John\'s movement.', ['John 3:1-36', 'Numbers 21:4-9', 'Jewish purification practices']),
            $this->john(4, $language, $version, 'Jesus travels through Samaria, speaks at Jacob\'s well near Sychar, and later returns to Cana where he heals an official\'s son.', 'The Samaritan setting carries long-standing tensions over ancestry, Scripture, and the proper mountain of worship. The well scene uses shared patriarchal memory to stage a conversation about worship, mission, and recognition of Jesus beyond Judea.', ['John 4:1-54', '2 Kings 17:24-41', 'Mount Gerizim']),
            $this->john(5, $language, $version, 'A Jerusalem healing near the Sheep Gate becomes a Sabbath dispute and a formal defense of Jesus\' authority.', 'The setting is shaped by public healing traditions, Sabbath observance, and legal testimony. Jesus answers opposition by appealing to the Father, his works, John the Baptist, Scripture, and Moses, giving the chapter the feel of a covenant lawsuit.', ['John 5:1-47', 'Deuteronomy 19:15', 'Sabbath controversy']),
            $this->john(6, $language, $version, 'Around Passover, Jesus feeds a multitude near the Sea of Galilee, walks on the sea, and teaches in Capernaum about the bread from heaven.', 'The chapter is saturated with Exodus memory: wilderness feeding, manna, sea crossing, and murmuring. Its synagogue setting in Capernaum makes the Bread of Life discourse a public test of whether hearers will receive Jesus as the fulfillment of God\'s provision.', ['John 6:1-71', 'Exodus 16:1-36', 'Psalm 78:24-25']),
            $this->john(7, $language, $version, 'Jesus goes to Jerusalem during the Feast of Tabernacles, where crowds and authorities debate his origin, learning, and messianic identity.', 'Tabernacles recalled wilderness dwelling and included water and light symbolism. Jesus\' invitation to drink in that festival setting draws on hopes for eschatological water, while the divided response reflects first-century debates about Scripture, Galilee, and messianic expectation.', ['John 7:1-52', 'Leviticus 23:33-43', 'Zechariah 14:8']),
            $this->john(8, $language, $version, 'John 8 continues the Jerusalem controversy with questions of judgment, witness, ancestry, freedom, and Jesus\' identity before Abraham.', 'The chapter belongs to the public dispute setting around the temple. Arguments about Abrahamic descent and truth reflect Jewish covenant identity debates, while Jesus\' claim of pre-existence intensifies the conflict to the point of attempted stoning.', ['John 8:1-59', 'Genesis 15:6', 'Exodus 3:14']),
            $this->john(9, $language, $version, 'A man born blind is healed, questioned by neighbors and authorities, expelled, and finally comes to fuller confession of Jesus.', 'The chapter unfolds like an inquiry with witnesses, parents, and authorities. Its background includes Sabbath dispute, assumptions about sin and disability, and synagogue discipline, turning a healing story into a study of public testimony and social cost.', ['John 9:1-41', 'Isaiah 35:5', 'Synagogue discipline']),
            $this->john(10, $language, $version, 'Jesus uses shepherd imagery and later speaks during the Feast of Dedication in Jerusalem.', 'Shepherd language recalls Israel\'s kings, leaders, and God\'s promised care in the prophets. The Dedication setting evokes the temple\'s rededication after the Maccabean crisis, sharpening questions about consecration, leadership, and Jesus\' unity with the Father.', ['John 10:1-42', 'Ezekiel 34:1-31', 'Feast of Dedication']),
            $this->john(11, $language, $version, 'The raising of Lazarus takes place in Bethany near Jerusalem and leads directly to an official decision to seek Jesus\' death.', 'Bethany\'s proximity to Jerusalem makes the sign highly visible among mourners and leaders. The story reflects Jewish mourning customs and resurrection hope, while the council response shows political fear under Roman occupation.', ['John 11:1-57', 'Daniel 12:2', 'Bethany near Jerusalem']),
            $this->john(12, $language, $version, 'Before Passover, Jesus is anointed at Bethany, enters Jerusalem as king, and gives his last public teaching.', 'The chapter combines burial preparation, royal procession, pilgrimage crowds, and the presence of Greeks seeking Jesus. These details signal that the Gospel\'s horizon is widening as Jesus\' hour approaches.', ['John 12:1-50', 'Zechariah 9:9', 'Isaiah 53:1']),
            $this->john(13, $language, $version, 'John 13 begins the farewell meal, where Jesus washes the disciples\' feet, identifies betrayal, and gives the new commandment.', 'The meal occurs in the shadow of Passover and replaces public signs with private instruction. Foot washing reflects household service and symbolic purification, preparing the disciples to understand leadership and love through the cross.', ['John 13:1-38', 'Passover meal setting', 'Leviticus 19:18']),
            $this->john(14, $language, $version, 'Jesus comforts the disciples about his departure, speaks of the Father\'s house, and promises the Paraclete.', 'The farewell discourse addresses fear, absence, and mission. Its background is the transition from Jesus\' earthly presence to the post-Easter life of the community, where memory, teaching, and obedience will be sustained by the Spirit.', ['John 14:1-31', 'Farewell discourse', 'John 14:26']),
            $this->john(15, $language, $version, 'The vine and branches discourse teaches abiding, fruitfulness, love, and the world\'s hostility toward the disciples.', 'The vine image recalls Israel as God\'s vineyard and reshapes covenant identity around attachment to Jesus. The warning about hatred reflects the social pressures faced by believers whose witness sets them apart.', ['John 15:1-27', 'Isaiah 5:1-7', 'Psalm 80:8-19']),
            $this->john(16, $language, $version, 'Jesus prepares the disciples for exclusion, sorrow, the Spirit\'s guidance, and joy after his return to the Father.', 'References to being put out of synagogues point to real communal conflict around confessing Jesus. The chapter frames persecution and grief within the coming work of the Spirit and the Easter reversal from sorrow to joy.', ['John 16:1-33', 'Synagogue exclusion', 'Isaiah 66:7-14']),
            $this->john(17, $language, $version, 'Jesus prays to the Father for glorification, the disciples\' protection and consecration, and the unity of future believers.', 'The prayer stands between the farewell discourse and the Passion. It gathers themes of mission, holiness, testimony, and unity, presenting Jesus as interceding for the community that will carry his witness into the world.', ['John 17:1-26', 'Psalm 133:1', 'Priestly intercession']),
            $this->john(18, $language, $version, 'The Passion begins in a garden, then moves through arrest, questioning by Jewish authorities, Peter\'s denial, and Pilate\'s praetorium.', 'The chapter is shaped by overlapping Jewish and Roman legal-political spaces. John emphasizes Jesus\' composure and kingship while showing the vulnerability of disciples and the pressure of imperial power.', ['John 18:1-40', 'Roman praetorium', 'Isaiah 53:7']),
            $this->john(19, $language, $version, 'Jesus is sentenced, crucified at Golgotha, dies, is pierced, and is buried before the Sabbath.', 'The historical setting includes Roman crucifixion, public execution outside the city, Passover preparation, and burial customs. John highlights royal irony, eyewitness testimony, and Scripture fulfillment through details such as the seamless tunic, hyssop, and unbroken bones.', ['John 19:1-42', 'Roman crucifixion', 'Exodus 12:46', 'Zechariah 12:10']),
            $this->john(20, $language, $version, 'On the first day of the week, the empty tomb and resurrection appearances lead from confusion to apostolic witness and Thomas\' confession.', 'The chapter reflects burial practices, the role of witnesses, and the emergence of Easter faith. Its purpose statement explains the Gospel as written testimony so readers may believe and have life.', ['John 20:1-31', 'Jewish burial customs', 'John 20:30-31']),
            $this->john(21, $language, $version, 'A Galilean lakeside appearance includes a miraculous catch, a shared meal, Peter\'s restoration, and a closing note about the beloved disciple\'s testimony.', 'The epilogue returns to fishing imagery and Galilee while clarifying pastoral mission after the Resurrection. Peter\'s commission, martyrdom saying, and the beloved disciple\'s witness locate the Gospel within apostolic memory.', ['John 21:1-25', 'Sea of Tiberias', 'John 21:15-24']),
        ];
    }

    /**
     * @param array<int, string> $chapterSources
     * @return array<string, mixed>
     */
    private function john(
        int $chapter,
        string $language,
        string $version,
        string $summary,
        string $details,
        array $chapterSources
    ): array {
        return [
            'book' => 'john',
            'chapter' => $chapter,
            'verse' => 1,
            'language' => $language,
            'version' => $version,
            'summary' => $summary,
            'details' => $details,
            'sources' => [
                [
                    'type' => 'biblical_text',
                    'title' => 'Catholic Public Domain Version',
                    'reference' => "John $chapter",
                    'url' => "https://sacredbible.org/catholic/NT-04_John.htm",
                ],
                [
                    'type' => 'introduction',
                    'title' => 'United States Conference of Catholic Bishops, Introduction to the Gospel According to John',
                    'reference' => 'Authorship, structure, and Johannine setting',
                    'url' => 'https://bible.usccb.org/bible/john/0',
                ],
                [
                    'type' => 'magisterial_document',
                    'title' => 'Second Vatican Council, Dei Verbum',
                    'reference' => 'Dei Verbum 18-19',
                    'url' => 'https://www.vatican.va/archive/hist_councils/ii_vatican_council/documents/vat-ii_const_19651118_dei-verbum_en.html',
                ],
                [
                    'type' => 'chapter_background',
                    'title' => 'Chapter background references',
                    'reference' => implode('; ', $chapterSources),
                ],
            ],
        ];
    }
}
