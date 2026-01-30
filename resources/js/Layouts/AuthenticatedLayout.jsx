import React, { useState, useContext, memo, useEffect, useMemo, useRef, useCallback } from 'react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import { Link, usePage, router } from '@inertiajs/react';
import { PlayerContext } from '@/contexts/PlayerContext';
import {
    UserIcon, MusicalNoteIcon, ArrowUpOnSquareIcon, ArrowRightOnRectangleIcon,
    ArrowsRightLeftIcon, QueueListIcon, XCircleIcon,
    RadioIcon, Bars3Icon, BellIcon,
    PlayIcon as IconoHeroReproducir,
    PauseIcon as IconoHeroPausar,
    BackwardIcon as IconoHeroRetroceder,
    ForwardIcon as IconoHeroAvanzar,
    SpeakerWaveIcon as IconoHeroVolumen,
    SpeakerXMarkIcon as IconoHeroVolumenMute,
    SpeakerWaveIcon as IconoHeroVolumenBajo,
    ArrowPathIcon as IconoHeroRepetir,
    ArrowPathRoundedSquareIcon as IconoHeroRepetirUno,
} from '@heroicons/react/24/outline';
import { ArrowPathIcon as IconoCarga } from '@heroicons/react/20/solid';


const IconoReproducir = (props) => <IconoHeroReproducir {...props} />;
const IconoPausar = (props) => <IconoHeroPausar {...props} />;
const IconoAnterior = (props) => <IconoHeroRetroceder {...props} />;
const IconoSiguiente = (props) => <IconoHeroAvanzar {...props} />;
const IconoVolumen = (props) => <IconoHeroVolumen {...props} />;
const IconoVolumenBajo = (props) => <IconoHeroVolumenBajo {...props} />;
const IconoVolumenMute = (props) => <IconoHeroVolumenMute {...props} />;
const IconoAleatorio = ArrowsRightLeftIcon;

const IconoRepetir = (props) => {
    const { activo, ...rest } = props;
    return (
        <img
            src={activo ? "/loop2.png" : "/loop1.png"}
            alt="Repetir"
            style={{ width: props?.className?.match(/h-(\d+)/) ? undefined : 20, height: props?.className?.match(/w-(\d+)/) ? undefined : 20 }}
            className={props.className}
        />
    );
};

const IconoRepetirUno = (props) => (
    <img
        src="/loop3.png"
        alt="Repetir una vez"
        style={{ width: props?.className?.match(/h-(\d+)/) ? undefined : 20, height: props?.className?.match(/w-(\d+)/) ? undefined : 20 }}
        className={props.className}
    />
);

const MAX_NOTIFICACIONES_A_MOSTRAR = 10;

const IconoLineasPersonalizado = (props) => (
    <svg {...props} viewBox="0 0 24 24" fill="currentColor">
        <rect x="7" y="5" width="2" height="14" />
        <rect x="11" y="5" width="2" height="14" />
        <line x1="16" y1="5" x2="19" y2="19" stroke="currentColor" strokeWidth="2" />
    </svg>
);

const formatearTiempo = (segundos) => {
    if (isNaN(segundos) || segundos < 0 || !isFinite(segundos)) return '0:00';
    const minutos = Math.floor(segundos / 60);
    const segundosRestantes = Math.floor(segundos % 60);
    return `${minutos}:${segundosRestantes.toString().padStart(2, '0')}`;
};

const obtenerUrlImagenDisposicion = (item) => {
    if (!item) return null;
    if (item.imagen) {
        return item.imagen.startsWith('http') ? item.imagen : `/storage/${item.imagen}`;
    }
    if (item?.foto_url) return item.foto_url;
    if (item?.image_url) return item.image_url;
    if (item?.album?.image_url) return item.album.image_url;
    if (item?.archivo_url && !item.imagen && !item.foto_url && !item.image_url) {
        return null;
    }
    return null;
};

const ImagenItemReproductor = memo(({ url, titulo, className = "w-10 h-10", iconoAlternativo, esItemCola = false }) => {
    const [src, setSrc] = useState(url);
    const [error, setError] = useState(false);

    useEffect(() => {
        setSrc(url);
        setError(false);
    }, [url]);

    const manejarErrorImagen = useCallback(() => {
        setError(true);
    }, []);

    const claseTamanoMarcador = esItemCola ? 'w-8 h-8' :
        className;
    const tamanoIconoMarcador = esItemCola ? 'h-4 w-4' : 'h-6 w-6';

    if (error || !src) {
        const claseFinal = `${className} bg-slate-700 flex items-center justify-center text-slate-500 rounded flex-shrink-0`;
        return (
            <div className={claseFinal}>
                {iconoAlternativo || <MusicalNoteIcon className={tamanoIconoMarcador} />}
            </div>
        );
    }

    return (
        <img
            src={src}
            alt={`Portada de ${titulo}`}
            className={`${className} object-cover rounded shadow-sm flex-shrink-0`}
            loading="lazy"
            onError={manejarErrorImagen}
        />
    );
});

ImagenItemReproductor.displayName = 'ImagenItemReproductor';

export default function AuthenticatedLayout({ children, header }) {
    const { auth } = usePage().props;
    const usuario = auth.user;
    const [mostrandoMenuMovil, setMostrandoMenuMovil] = useState(false);
    const [esColaVisible, setEsColaVisible] = useState(false);
    const [consultaBusqueda, setConsultaBusqueda] = useState('');
    const [esNotificacionesVisible, setEsNotificacionesVisible] = useState(false);
    const [notificaciones, setNotificaciones] = useState([]);
    const [contadorNotificacionesNoLeidas, setContadorNotificacionesNoLeidas] = useState(0);

    const valorContextoReproductor = useContext(PlayerContext);
    const {
        cancionActual, cancionActualIndex, Reproduciendo, tiempoActual, duration, volumen,
        aleatorio, looping, loopingOne, cargando, playerError, sourceId,
        play, pause, siguienteCancion, anteriorCancion,
        busqueda, setVolumen, toggleAleatorio, toggleLoop,
        playCola, limpiarErrores, queue = []
    } = valorContextoReproductor || {};

    const refBotonCola = useRef(null);
    const refDropdownCola = useRef(null);
    const refBotonNotificaciones = useRef(null);
    const refDropdownNotificaciones = useRef(null);

    const accionesReproductorDeshabilitadas = useMemo(() => {
        return cargando || (!cancionActual && queue.length === 0);
    }, [cargando, cancionActual, queue.length]);

    useEffect(() => {
        function handleClickOutside(event) {
            if (refDropdownCola.current && !refDropdownCola.current.contains(event.target) &&
                refBotonCola.current && !refBotonCola.current.contains(event.target)) {
                setEsColaVisible(false);
            }
        }

        if (esColaVisible) {
            document.addEventListener("mousedown", handleClickOutside);
        } else {
            document.removeEventListener("mousedown", handleClickOutside);
        }
        return () => {
            document.removeEventListener("mousedown", handleClickOutside);
        };
    }, [esColaVisible]);

    useEffect(() => {
        function handleClickOutsideNotifications(event) {
            if (refDropdownNotificaciones.current && !refDropdownNotificaciones.current.contains(event.target) &&
                refBotonNotificaciones.current && !refBotonNotificaciones.current.contains(event.target)) {
                setEsNotificacionesVisible(false);
            }
        }

        if (esNotificacionesVisible) {
            document.addEventListener("mousedown", handleClickOutsideNotifications);
        } else {
            document.removeEventListener("mousedown", handleClickOutsideNotifications);
        }
        return () => {
            document.removeEventListener("mousedown", handleClickOutsideNotifications);
        };
    }, [esNotificacionesVisible]);

    const obtenerNotificaciones = useCallback(async () => {
        if (usuario) {
            try {
                const response = await fetch(route('notificaciones.index'));
                if (!response.ok) {
                    throw new Error(`Error HTTP! estado: ${response.status}`);
                }
                const data = await response.json();
                const ultimasNotificaciones = data.notificaciones.slice(-MAX_NOTIFICACIONES_A_MOSTRAR);
                setNotificaciones(ultimasNotificaciones);
                setContadorNotificacionesNoLeidas(data.no_leidas);
            } catch (error) {
                console.error('Error al obtener notificaciones:', error);
            }
        }
    }, [usuario]);

    useEffect(() => {
        obtenerNotificaciones();
        const intervalId = setInterval(obtenerNotificaciones, 60000);
        return () => clearInterval(intervalId);
    }, [obtenerNotificaciones]);

    const marcarNotificacionComoLeida = useCallback(async (idNotificacion) => {
        try {
            const response = await fetch(route('notificaciones.marcarComoLeida', idNotificacion), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
            });
            const data = await response.json();

            if (data.success) {
                setNotificaciones(prevNotificaciones =>
                    prevNotificaciones.map(notif =>
                        notif.id === idNotificacion ? { ...notif, leido: true } : notif
                    )
                );
                setContadorNotificacionesNoLeidas(prevCount => Math.max(0, prevCount - 1));
            } else {
                console.error('Error al marcar notificación como leída:', data.message);
            }
        } catch (error) {
            console.error('Error al marcar notificación como leída:', error);
        }
    }, []);

    const marcarTodasNotificacionesComoLeidas = useCallback(async () => {
        try {
            const response = await fetch(route('notificaciones.marcarTodasComoLeidas'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
            });
            const data = await response.json();

            if (data.success) {
                setNotificaciones(prevNotificaciones =>
                    prevNotificaciones.map(notif => ({ ...notif, leido: true }))
                );
                setContadorNotificacionesNoLeidas(0);
            } else {
                console.error('Error al marcar todas las notificaciones como leídas:', data.message);
            }
        } catch (error) {
            console.error('Error en la solicitud para marcar todas como leídas:', error);
        }
    }, []);


    const [valorBusquedaTiempo, setValorBusquedaTiempo] = useState(tiempoActual);
    const estaBuscandoRef = useRef(false);
    useEffect(() => {
        if (!estaBuscandoRef.current) {
            setValorBusquedaTiempo(tiempoActual);
        }
    }, [tiempoActual]);

    const manejarCambioBusquedaTiempo = (e) => {
        const nuevoValor = parseFloat(e.target.value);
        setValorBusquedaTiempo(nuevoValor);
        estaBuscandoRef.current = true;
    };

    const confirmarBusquedaTiempo = () => {
        if (busqueda) {
            busqueda(valorBusquedaTiempo);
        }
        estaBuscandoRef.current = false;
    };

    const manejarCambioVolumen = (e) => {
        const nuevoVolumen = parseFloat(e.target.value);
        if (setVolumen) {
            setVolumen(nuevoVolumen);
        }
    };

    const alternarReproducirPausar = () => {
        if (!valorContextoReproductor) {
            return;
        }
        if (!cancionActual && queue.length > 0) {
            play();
        } else if (Reproduciendo) {
            pause();
        } else {
            play();
        }
    };

    const IconoVolumenActual = useMemo(() => {
        if (volumen === 0) return IconoVolumenMute;
        if (volumen < 0.5) return IconoVolumenBajo;
        return IconoVolumen;
    }, [volumen]);

    const porcentajeProgreso = useMemo(() => (duration > 0 && isFinite(tiempoActual) && isFinite(duration) ? (tiempoActual / duration) * 100 : 0), [tiempoActual, duration]);
    const estiloBarraProgreso = useMemo(() => ({ background: `linear-gradient(to right, #007FFF ${porcentajeProgreso}%, #4a5568 ${porcentajeProgreso}%)` }), [porcentajeProgreso]);

    const urlImagenPistaActual = obtenerUrlImagenDisposicion(cancionActual);
    const artistaPistaActual = useMemo(() => {
    if (!cancionActual) return (
        <span className="text-gray-400">Artista Desconocido</span>
    );

    if (cancionActual.usuarios && cancionActual.usuarios.length > 0) {
        return cancionActual.usuarios.map((u, idx) => (
            <React.Fragment key={u.id}>
                <Link href={route('profile.show', u.id)} className="text-gray-400 font-semibold transition group hover:text-blue-300 hover:underline hover:underline-offset-2 hover:decoration-blue-300">
                    <span className="relative group">
                        {u.name}
                        <span className="absolute left-0 -bottom-0.5 w-full h-0.5 bg-blue-400 opacity-0 group-hover:opacity-100 transition-opacity"></span>
                    </span>
                </Link>
                {idx < cancionActual.usuarios.length - 1 && ', '}
            </React.Fragment>
        ));
    }

    if (cancionActual.artista) return <span className="text-gray-400">{cancionActual.artista}</span>;
    if (cancionActual.album?.artista) return <span className="text-gray-400">{cancionActual.album.artista}</span>;
    return <span className="text-gray-400">Artista Desconocido</span>;
}, [cancionActual]);


    const manejarClickReproducirDesdeCola = (index) => {
        if (playCola) {
            playCola(index);
            setEsColaVisible(false);
        }
    };

    const manejarEnvioBusqueda = (e) => {
        if (e.key === 'Enter' && consultaBusqueda.trim() !== '') {
            router.get(route('search.index', { query: consultaBusqueda }));
        }
    };

    const tieneCola = queue && queue.length > 0 || cancionActual;
    const paddingInferiorPrincipal = tieneCola ?
        'pb-24 md:pb-28' : 'pb-5';

    const IconoBotonBucle = useMemo(() => {
        if (loopingOne) {
            return IconoRepetirUno;
        } else if (looping) {
            return IconoRepetir;
        }
        return IconoRepetir;
    }, [looping, loopingOne]);

    const tituloBotonBucle = useMemo(() => {
        if (loopingOne) {
            return "Repetir una canción";
        } else if (looping) {
            return "Repetir cola";
        }
        return "Activar repetición";
    }, [looping, loopingOne]);

    const claseBotonBucle = useMemo(() => {
        let base = 'p-1 rounded-full transition-colors duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 focus:ring-offset-slate-900 inline-flex';
        if (accionesReproductorDeshabilitadas) {
            base += ' opacity-50 cursor-not-allowed';
        } else if (looping || loopingOne) {
            base += ' text-blue-500 hover:text-blue-400';
        } else {
            base += ' text-gray-400 hover:text-blue-400';
        }
        return base;
    }, [looping, loopingOne, accionesReproductorDeshabilitadas]);

    return (
        <div className="min-h-screen bg-gradient-to-b from-gray-900 to-black text-gray-300 font-sans">
            <header className="fixed top-0 left-0 right-0 z-50 bg-black/80 backdrop-blur-md shadow-lg text-white">
                <div className="container mx-auto px-4 sm:px-6 py-3 flex items-center justify-between">
                    <div className="flex-shrink-0">
                        <Link href="/" className="text-2xl font-bold text-blue-500 hover:text-blue-400 transition-colors">
                            <ApplicationLogo className="h-8 w-auto" />
                        </Link>
                    </div>
                    <div className="hidden md:flex flex-grow items-center justify-center space-x-6">
                        <Link href={route('biblioteca')} className="text-sm hover:text-blue-400 transition-colors flex flex-col items-center group">
                            <IconoLineasPersonalizado className="h-8 w-8 text-gray-300 group-hover:text-blue-400 transition-colors" />
                        </Link>
                        <div className="relative w-full max-w-md">
                            <input
                                type="search"
                                value={consultaBusqueda}
                                onChange={(e) => setConsultaBusqueda(e.target.value)}
                                onKeyDown={manejarEnvioBusqueda}
                                placeholder="Buscar..."
                                aria-label="Campo de búsqueda"
                                className="w-full px-4 py-2 text-sm text-gray-200 bg-gray-700/50 border border-gray-600 rounded-full focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-gray-700 placeholder-gray-400 transition-colors"
                            />
                        </div>
                        <Link href={route('radio')} className="text-sm hover:text-blue-400 transition-colors flex flex-col items-center group">
                            <RadioIcon className="h-8 w-8 text-gray-300 group-hover:text-blue-400 transition-colors" />
                        </Link>
                    </div>
                    {usuario && (
                        <div className="hidden md:flex items-center space-x-4">
                            <div className="relative">
                                <button
                                    ref={refBotonNotificaciones}
                                    onClick={() => {
                                        setEsNotificacionesVisible(!esNotificacionesVisible);
                                        if (!esNotificacionesVisible) {
                                            obtenerNotificaciones();
                                        }
                                    }}
                                    className="p-1 rounded-full text-gray-300 hover:text-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-slate-900"
                                    aria-label="Notificaciones"
                                >
                                    <BellIcon className="h-6 w-6" />
                                    {contadorNotificacionesNoLeidas > 0 && (
                                        <span className="absolute top-0 right-0 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-red-100 transform translate-x-1/2 -translate-y-1/2 bg-red-600 rounded-full">
                                            {contadorNotificacionesNoLeidas}
                                        </span>
                                    )}
                                </button>

                                {esNotificacionesVisible && (
                                    <div
                                        ref={refDropdownNotificaciones}
                                        className="absolute top-full right-0 mt-2 w-72 max-h-96 overflow-y-auto bg-slate-800 border border-slate-700 rounded-lg shadow-xl z-50 p-2"
                                    >
                                        <h4 className="text-sm font-semibold text-gray-300 px-2 pb-2 border-b border-slate-700">Notificaciones</h4>
                                        {notificaciones.length === 0 ? (
                                            <p className="text-gray-400 text-sm p-2">No tienes notificaciones.</p>
                                        ) : (
                                            <ul className="divide-y divide-slate-700">
                                                {notificaciones.map((notificacion) => (
                                                    <li
                                                        key={notificacion.id}
                                                        className={`p-2 text-sm relative ${
                                                            notificacion.leido
                                                                ? 'bg-white dark:bg-gray-800 text-gray-500 after:absolute after:inset-0 after:bg-black after:opacity-60 after:rounded-md'
                                                                : 'bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700/70 text-gray-900 dark:text-gray-100'
                                                        } cursor-pointer transition-colors rounded-md`}
                                                        onClick={() => marcarNotificacionComoLeida(notificacion.id)}
                                                        style={{ overflow: 'hidden' }}
                                                    >
                                                        <div className="relative z-10">
                                                            <p className="font-medium text-blue-300">{notificacion.titulo}</p>
                                                            <p
                                                                className="text-gray-300"
                                                                dangerouslySetInnerHTML={{ __html: notificacion.mensaje }}
                                                            ></p>
                                                            <p className="text-xs text-gray-500 mt-1">{new Date(notificacion.created_at).toLocaleString()}</p>
                                                        </div>
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                        {contadorNotificacionesNoLeidas > 0 && (
                                            <button
                                                onClick={marcarTodasNotificacionesComoLeidas}
                                                className="mt-4 w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 focus:ring-offset-slate-900"
                                            >
                                                Marcar todas como leídas
                                            </button>
                                        )}
                                    </div>
                                )}
                            </div>

                            <Dropdown>
                                <Dropdown.Trigger>
                                    <button className="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-gray-300 hover:text-blue-400 focus:outline-none transition ease-in-out duration-150">
                                        {usuario.name}
                                        <svg className="ml-2 -mr-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fillRule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clipRule="evenodd" />
                                        </svg>
                                    </button>
                                </Dropdown.Trigger>
                                <Dropdown.Content>
                                    <Dropdown.Link href={route('profile.show', usuario.id)} className="flex items-center">
                                        <UserIcon className="h-4 w-4 mr-2" />
                                        Perfil
                                    </Dropdown.Link>

                                    { (auth.user && auth.user.email === 'ptazet4@gmail.com') || (auth.user && auth.user.roles && auth.user.roles.some(rol => rol.nombre === 'Administrador')) ? (
                                        <Dropdown.Link href={route('roles.index')} method="get" className="flex items-center">
                                            <MusicalNoteIcon className="h-4 w-4 mr-2" />
                                            Administración
                                        </Dropdown.Link>
                                    ) : null}
                                    <div className="flex items-center opacity-50 cursor-not-allowed px-3 py-2 text-sm text-gray-400" title="Funcionalidad deshabilitada">
                                        <MusicalNoteIcon className="h-4 w-4 mr-2" />
                                        Subir canción
                                    </div>
                                    <div className="flex items-center opacity-50 cursor-not-allowed px-3 py-2 text-sm text-gray-400" title="Funcionalidad deshabilitada">
                                        <ArrowUpOnSquareIcon className="h-4 w-4 mr-2" />
                                        Subir lanzamiento
                                    </div>
                                    <Dropdown.Link href={route('logout')} method="post" as="button" className="flex items-center">
                                        <ArrowRightOnRectangleIcon className="h-4 w-4 mr-2" />
                                        Cerrar sesión
                                    </Dropdown.Link>
                                </Dropdown.Content>
                            </Dropdown>
                        </div>
                    )}
                    <div className="md:hidden flex items-center">
                        <button onClick={() => setMostrandoMenuMovil(!mostrandoMenuMovil)} className="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-white hover:bg-gray-700 focus:outline-none transition duration-150 ease-in-out" aria-label="Alternar menú móvil">
                            <Bars3Icon className="h-6 w-6" />
                        </button>
                    </div>
                </div>
                {mostrandoMenuMovil && (
                    <div className="md:hidden border-t border-gray-700/50 pt-2 pb-3 space-y-1">
                        <div className="px-4 mb-4">
                            <input
                                type="search"
                                value={consultaBusqueda}
                                onChange={(e) => setConsultaBusqueda(e.target.value)}
                                onKeyDown={manejarEnvioBusqueda}
                                placeholder="Buscar..."
                                aria-label="Campo de búsqueda móvil"
                                className="w-full px-4 py-2.5 text-sm text-gray-200 bg-gray-700/50 border border-gray-600 rounded-full focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-gray-700 placeholder-gray-400 transition-colors"
                            />
                        </div>

                        {usuario ? (
                            <div className="px-4 pb-3 border-b border-gray-700/50 mb-3">
                                <Link href={route('profile.show', usuario.id)} className="flex items-center space-x-3 text-white">
                                    <img
                                        src={usuario.foto_perfil}
                                        alt={usuario.name}
                                        className="h-10 w-10 rounded-full object-cover"
                                    />
                                    <span className="text-base font-medium">{usuario.name}</span>
                                </Link>
                                <div className="relative mt-3">
                                    <button
                                        onClick={() => {
                                            setEsNotificacionesVisible(!esNotificacionesVisible);
                                            if (!esNotificacionesVisible) {
                                                obtenerNotificaciones();
                                            }
                                        }}
                                        className="w-full text-left block px-3 py-2 rounded-md text-base font-medium text-gray-300 hover:text-white hover:bg-gray-700/50 flex items-center space-x-2"
                                        aria-label="Notificaciones"
                                    >
                                        <BellIcon className="h-5 w-5" />
                                        <span>Notificaciones</span>
                                        {contadorNotificacionesNoLeidas > 0 && (
                                            <span className="ml-auto inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-red-100 bg-red-600 rounded-full">
                                                {contadorNotificacionesNoLeidas}
                                            </span>
                                        )}
                                    </button>
                                    {esNotificacionesVisible && (
                                        <div
                                            className="mt-2 w-full max-h-60 overflow-y-auto bg-slate-800 border border-slate-700 rounded-lg shadow-xl z-50 p-2"
                                        >
                                            <h4 className="text-sm font-semibold text-gray-300 px-2 pb-2 border-b border-slate-700">Notificaciones</h4>
                                            {notificaciones.length === 0 ? (
                                                <p className="text-gray-400 text-sm p-2">No tienes notificaciones.</p>
                                            ) : (
                                                <ul className="divide-y divide-slate-700">
                                                    {notificaciones.map((notificacion) => (
                                                        <li
                                                            key={notificacion.id}
                                                            className={`p-2 text-sm relative ${
                                                                notificacion.leido
                                                                    ? 'bg-white dark:bg-gray-800 text-gray-500 after:absolute after:inset-0 after:bg-black after:opacity-60 after:rounded-md'
                                                                    : 'bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700/70 text-gray-900 dark:text-gray-100'
                                                            } cursor-pointer transition-colors rounded-md`}
                                                            onClick={() => marcarNotificacionComoLeida(notificacion.id)}
                                                            style={{ overflow: 'hidden' }}
                                                        >
                                                            <div className="relative z-10">
                                                                <p className="font-medium text-blue-300">{notificacion.titulo}</p>
                                                                <p className="text-gray-300">{notificacion.mensaje}</p>
                                                                <p className="text-xs text-gray-500 mt-1">{new Date(notificacion.created_at).toLocaleString()}</p>
                                                            </div>
                                                        </li>
                                                    ))}
                                                </ul>
                                            )}
                                            {contadorNotificacionesNoLeidas > 0 && (
                                                <button
                                                    onClick={marcarTodasNotificacionesComoLeidas}
                                                    className="mt-4 w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 focus:ring-offset-slate-900"
                                                >
                                                    Marcar todas como leídas
                                                </button>
                                            )}
                                        </div>
                                    )}
                                </div>
                            </div>
                        ) : (
                            <div className="px-4 pb-3 border-b border-gray-700/50 mb-3">
                                <Link href={route('login')} className="block py-2 text-base font-medium text-gray-300 hover:text-white hover:bg-gray-700/50 rounded-md flex items-center space-x-2">
                                    <UserIcon className="h-5 w-5" />
                                    <span>Iniciar Sesión</span>
                                </Link>
                                <Link href={route('register')} className="block mt-2 py-2 text-base font-medium bg-blue-600 hover:bg-blue-700 text-white rounded-md text-center flex items-center justify-center space-x-2">
                                    <UserIcon className="h-5 w-5" />
                                    <span>Registrarse</span>
                                </Link>
                            </div>
                        )}

                        <div className="space-y-1 px-2 pb-3 border-b border-gray-700/50 mb-3">
                            <h5 className="text-xs uppercase text-gray-500 font-semibold px-2 mb-1">Navegación</h5>
                            <Link href={route('biblioteca')} className="block px-3 py-2 rounded-md text-base font-medium text-gray-300 hover:text-white hover:bg-gray-700/50 flex items-center space-x-2">
                                <IconoLineasPersonalizado className="h-5 w-5" />
                                <span>Biblioteca</span>
                            </Link>
                            <Link href={route('radio')} className="block px-3 py-2 rounded-md text-base font-medium text-gray-300 hover:text-white hover:bg-gray-700/50 flex items-center space-x-2">
                                <RadioIcon className="h-5 w-5" />
                                <span>Radio</span>
                            </Link>
                        </div>

                        {usuario && (
                            <div className="space-y-1 px-2 pb-3 border-b border-gray-700/50 mb-3">
                                <h5 className="text-xs uppercase text-gray-500 font-semibold px-2 mb-1">Tus Contenidos</h5>
                                <div className="block px-3 py-2 rounded-md text-base font-medium text-gray-400 opacity-50 cursor-not-allowed flex items-center space-x-2" title="Funcionalidad deshabilitada">
                                    <MusicalNoteIcon className="h-5 w-5" />
                                    <span>Subir canción</span>
                                </div>
                                <div className="block px-3 py-2 rounded-md text-base font-medium text-gray-400 opacity-50 cursor-not-allowed flex items-center space-x-2" title="Funcionalidad deshabilitada">
                                    <ArrowUpOnSquareIcon className="h-5 w-5" />
                                    <span>Subir lanzamiento</span>
                                </div>
                            </div>
                        )}

                        {usuario && (
                            <div className="space-y-1 px-2">
                                <h5 className="text-xs uppercase text-gray-500 font-semibold px-2 mb-1">Sesión</h5>
                                <button onClick={() => { router.post(route('logout'));
                                }} className="w-full text-left block px-3 py-2 rounded-md text-base font-medium text-gray-300 hover:text-white hover:bg-gray-700/50 flex items-center space-x-2" aria-label="Cerrar sesión">
                                    <ArrowRightOnRectangleIcon className="h-5 w-5" />
                                    <span>Cerrar sesión</span>
                                </button>
                            </div>
                        )}
                    </div>
                )}
            </header>

            {header && (
                <header className="bg-slate-800 shadow pt-16">
                    <div className="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {header}
                    </div>
                </header>
            )}

            <main className={`pt-0 ${paddingInferiorPrincipal}`}>
                {children}
            </main>


            {tieneCola && (
                <footer className="fixed bottom-0 left-0 right-0 z-40 bg-gradient-to-r from-black via-gray-900/95 to-black backdrop-blur-lg border-t border-blue-500/30 text-white shadow-lg flex flex-col">
                    {playerError && (
                        <div className="bg-red-800/80 text-white text-xs text-center py-1 px-4 flex justify-between items-center">
                            <span>{playerError}</span>
                            <button onClick={limpiarErrores} className="p-0.5 hover:bg-red-700 rounded-full focus:outline-none focus:ring-1 focus:ring-white" aria-label="Cerrar error de reproductor">
                                <XCircleIcon className="h-4 w-4" />
                            </button>
                        </div>
                    )}

                    <div className="w-full px-2 pt-1 md:hidden">
                        <input
                            type="range"
                            min="0"
                            max={duration || 0}
                            value={valorBusquedaTiempo}
                            onChange={manejarCambioBusquedaTiempo}
                            onMouseUp={confirmarBusquedaTiempo}
                            onTouchEnd={confirmarBusquedaTiempo}
                            disabled={!cancionActual || !duration || accionesReproductorDeshabilitadas}
                            aria-label="Barra de progreso de la canción"
                            className="w-full h-1 rounded-lg appearance-none cursor-pointer range-progress-gradient"
                            style={estiloBarraProgreso}
                        />
                    </div>

                    <div className="container mx-auto w-full px-3 sm:px-4 py-2 flex items-center justify-between space-x-2 sm:space-x-3">
                        <div className="flex items-center space-x-2 flex-1 min-w-0 md:flex-initial md:w-1/4 lg:w-1/3 md:space-x-3">
                            <ImagenItemReproductor url={obtenerUrlImagenDisposicion(cancionActual)} titulo={cancionActual?.titulo || ''} className="w-10 h-10 md:w-12 md:h-12" />
                            <div className="overflow-hidden hidden sm:block">
                                <p className="text-sm font-medium text-blue-400 truncate" title={cancionActual?.titulo || 'Ninguna Canción'}>
                                    {cancionActual?.titulo || 'Ninguna Canción'}
                                </p>
                                <p className="text-xs text-gray-400 truncate hidden md:block" title={artistaPistaActual}>{artistaPistaActual}</p>
                            </div>
                        </div>

                        <div className="flex flex-col items-center md:flex-grow">
                            <div className="flex items-center space-x-2 sm:space-x-3 md:space-x-4">
                                <button
                                    onClick={toggleAleatorio}
                                    title={aleatorio ? "Desactivar modo aleatorio" : "Activar modo aleatorio"}
                                    aria-label={aleatorio ? "Desactivar modo aleatorio" : "Activar modo aleatorio"}
                                    className={`p-1 rounded-full transition-colors duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 focus:ring-offset-slate-900 inline-flex ${aleatorio ? 'text-blue-500 hover:text-blue-400' : 'text-gray-400 hover:text-blue-400'} ${accionesReproductorDeshabilitadas ? 'opacity-50 cursor-not-allowed' : ''}`}
                                    disabled={accionesReproductorDeshabilitadas}
                                >
                                    <IconoAleatorio className="h-5 w-5" />
                                </button>

                                <button
                                    onClick={anteriorCancion}
                                    disabled={accionesReproductorDeshabilitadas}
                                    aria-label="Canción anterior"
                                    className={`text-gray-400 hover:text-blue-400 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-slate-900 p-1 ${accionesReproductorDeshabilitadas ? 'opacity-50 cursor-not-allowed' : ''}`}
                                >
                                    <IconoAnterior className="h-5 w-5" />
                                </button>

                                <button
                                    onClick={alternarReproducirPausar}
                                    disabled={accionesReproductorDeshabilitadas}
                                    aria-label={Reproduciendo ? "Pausar reproducción" : "Reproducir canción"}
                                    className={`bg-blue-600 hover:bg-blue-500 rounded-full text-white transition-colors shadow-lg flex items-center justify-center w-10 h-10 sm:w-11 sm:h-11 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 focus:ring-offset-slate-900 ${accionesReproductorDeshabilitadas ? 'opacity-50 cursor-not-allowed' : ''}`}
                                >
                                    {cargando ?
                                        (
                                            <IconoCarga className="h-5 w-5 sm:h-6 sm:w-6 animate-spin" />
                                        ) : Reproduciendo ?
                                            (
                                                <IconoPausar className="h-5 w-5 sm:h-6 sm:w-6" />
                                            ) : (
                                                <IconoReproducir className="h-5 w-5 sm:h-6 w-6" />
                                            )}
                                </button>

                                <button
                                    onClick={siguienteCancion}
                                    disabled={accionesReproductorDeshabilitadas}
                                    aria-label="Siguiente canción"
                                    className={`text-gray-400 hover:text-blue-400 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-slate-900 p-1 ${accionesReproductorDeshabilitadas ? 'opacity-50 cursor-not-allowed' : ''}`}
                                >
                                    <IconoSiguiente className="h-5 w-5" />
                                </button>

                                <button
                                    onClick={toggleLoop}
                                    title={tituloBotonBucle}
                                    aria-label={tituloBotonBucle}
                                    className={claseBotonBucle}
                                    disabled={accionesReproductorDeshabilitadas}
                                >
                                    {
                                        IconoBotonBucle === IconoRepetir
                                            ? <IconoRepetir className="h-5 w-5" activo={looping || loopingOne} />
                                            : <IconoBotonBucle className="h-5 w-5" />
                                    }
                                </button>
                            </div>

                            <div className="w-full max-w-xl hidden md:flex items-center space-x-2 mt-1">
                                <span className="text-xs text-gray-500 font-mono w-10 text-right">{formatearTiempo(tiempoActual)}</span>
                                <input
                                    type="range"
                                    min="0"
                                    max={duration || 0}
                                    value={valorBusquedaTiempo}
                                    onChange={manejarCambioBusquedaTiempo}
                                    onMouseUp={confirmarBusquedaTiempo}
                                    onTouchEnd={confirmarBusquedaTiempo}
                                    disabled={!cancionActual || !duration || accionesReproductorDeshabilitadas}
                                    aria-label="Barra de progreso de la canción"
                                    className={`w-full h-1.5 rounded-lg appearance-none cursor-pointer range-progress-gradient ${accionesReproductorDeshabilitadas ? 'opacity-50 cursor-not-allowed' : ''}`}
                                    style={estiloBarraProgreso}
                                />
                                <span className="text-xs text-gray-500 font-mono w-10 text-left">{formatearTiempo(duration)}</span>
                            </div>
                        </div>

                        <div className="flex items-center justify-end space-x-2 flex-1 md:flex-initial md:w-1/4 lg:w-1/3">
                            <div className="hidden lg:flex items-center space-x-2">
                                <button
                                    className={`text-gray-400 hover:text-blue-400 transition-colors p-1 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-slate-900 ${accionesReproductorDeshabilitadas ? 'opacity-50 cursor-not-allowed' : ''}`}
                                    aria-label="Botón de volumen"
                                    disabled={accionesReproductorDeshabilitadas}
                                >
                                    <IconoVolumenActual className="h-5 w-5" />
                                </button>
                                <input
                                    type="range"
                                    min="0"
                                    max="1"
                                    step="0.01"
                                    value={volumen}
                                    onChange={manejarCambioVolumen}
                                    aria-label="Control deslizante de volumen"
                                    className={`w-20 h-1.5 bg-gray-600 rounded-lg appearance-none cursor-pointer range-sm accent-blue-500 hover:accent-blue-400 ${accionesReproductorDeshabilitadas ? 'opacity-50 cursor-not-allowed' : ''}`}
                                    disabled={accionesReproductorDeshabilitadas}
                                />
                            </div>

                            <button
                                ref={refBotonCola}
                                onClick={() => setEsColaVisible(!esColaVisible)}
                                title="Mostrar cola de reproducción"
                                aria-label="Mostrar cola de reproducción"
                                className={`text-gray-400 hover:text-blue-400 transition-colors p-1 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-slate-900 ${accionesReproductorDeshabilitadas ? 'opacity-50 cursor-not-allowed' : ''}`}
                                disabled={accionesReproductorDeshabilitadas}
                            >
                                <QueueListIcon className="h-5 w-5" />
                            </button>

                            {esColaVisible && (
                                <div
                                    ref={refDropdownCola}
                                    className="absolute bottom-full right-0 mb-2 w-64 sm:w-80 max-h-80 overflow-y-auto bg-slate-800 border border-slate-700 rounded-lg shadow-xl z-50 p-2"
                                >
                                    <h4 className="text-sm font-semibold text-gray-300 px-2 pb-2 border-b border-slate-700">Cola de Reproducción</h4>
                                    {queue.length === 0 && cancionActual ? (
                                        <p className="text-gray-400 text-sm p-2">La cola está vacía. Reproduciendo solo la canción actual.</p>
                                    ) : queue.length === 0 ?
                                        (
                                            <p className="text-gray-400 text-sm p-2">La cola está vacía.</p>
                                        ) : (
                                            <ul className="divide-y divide-slate-700">
                                                {queue.map((cancion, index) => (
                                                    <li key={cancion.id || index} className={`flex items-center p-2 text-sm ${index === cancionActualIndex ? 'bg-blue-900/50 text-blue-300' : 'hover:bg-slate-700/50'} cursor-pointer transition-colors rounded-md group`} onClick={() => manejarClickReproducirDesdeCola(index)}>
                                                        <ImagenItemReproductor url={obtenerUrlImagenDisposicion(cancion)} titulo={cancion.titulo} className="w-8 h-8 mr-2" esItemCola={true} />
                                                        <div className="flex-grow overflow-hidden">
                                                            <p className="font-medium truncate">{cancion.titulo}</p>
                                                        </div>
                                                        {index === cancionActualIndex && Reproduciendo && (
                                                            <MusicalNoteIcon className="h-4 w-4 ml-2 text-blue-400 animate-pulse" />
                                                        )}
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                </div>
                            )}
                        </div>
                    </div>
                </footer>
            )}
        </div>
    );
}
