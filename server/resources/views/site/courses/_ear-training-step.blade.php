{{-- One step of the scale over an Ear training section (site/courses/ear-training): its number and its name (do re mi)
     for the eye, its label for everyone. $n is the step, 1 to 8; $ink is set on the dark band. --}}
<p @class(['cp-et-step', 'cp-et-step-ink' => $ink ?? false])><span class="cp-et-step-n" aria-hidden="true">{{ $n }}</span><em aria-hidden="true">{{ $et['sol'][$n - 1] ?? '' }}</em><span>{{ $et['steps'][$n - 1] ?? '' }}</span></p>
