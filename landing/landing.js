// Landing Page Component
const LandingPage = () => {
  // Estados
  const [scrollY, setScrollY] = React.useState(0);
  const [activeSection, setActiveSection] = React.useState(0);
  const [expandedFaq, setExpandedFaq] = React.useState(null);

  // Secciones de contenido principal
  const mainSections = [
      {
          title: "Descubre Tu Próxima Aventura",
          subtitle: "Explora los Pirineos con un asistente AI personalizado",
          icon: "mountain",
          features: [
              "Rutas personalizadas según tu nivel",
              "Información meteorológica en tiempo real",
              "Recomendaciones de seguridad",
              "Más de 1000 rutas disponibles"
          ],
          imageUrl: "paginasespecializadas/skitouring/bannerski.jpg"
      },
      {
          title: "IA que Entiende tus Necesidades",
          subtitle: "Conversaciones naturales para encontrar la ruta perfecta",
          icon: "robot",
          features: [
              "Asistente conversacional intuitivo",
              "Sugerencias basadas en tus preferencias",
              "Aprende de tus interacciones",
              "Respuestas precisas y contextuales"
          ],
          imageUrl: "paginasespecializadas/skitouring/bannerski.jpg"
      },
      {
          title: "Únete a la Comunidad",
          subtitle: "Comparte experiencias y encuentra compañeros de ruta",
          icon: "users",
          features: [
              "Comparte tus rutas favoritas",
              "Valoraciones y comentarios",
              "Consejos de otros usuarios",
              "Eventos y quedadas"
          ],
          imageUrl: "paginasespecializadas/skitouring/bannerski.jpg"
      },
      {
          title: "Seguridad Primero",
          subtitle: "Planifica tus aventuras con confianza",
          icon: "shield-alt",
          features: [
              "Alertas meteorológicas",
              "Consejos de seguridad",
              "Estado actual de las rutas",
              "Equipamiento recomendado"
          ],
          imageUrl: "paginasespecializadas/skitouring/bannerski.jpg"
      }
  ];

  // Testimonios
  const testimonials = [
      {
          name: "Ana G.",
          role: "Senderista entusiasta",
          text: "Kahir.AI ha revolucionado la forma en que planeo mis rutas. Las recomendaciones son siempre acertadas.",
          image: "/api/placeholder/64/64"
      },
      {
          name: "Carlos M.",
          role: "Guía de montaña",
          text: "Una herramienta imprescindible para cualquier amante de la montaña. La información meteorológica es especialmente útil.",
          image: "/api/placeholder/64/64"
      },
      {
          name: "Laura S.", 
          role: "Esquiadora",
          text: "Las recomendaciones de rutas de esquí son excelentes. La IA realmente entiende mis preferencias.",
          image: "/api/placeholder/64/64"
      }
  ];

  // Estadísticas
  const stats = [
      { number: "1000+", label: "Rutas disponibles" },
      { number: "50K+", label: "Usuarios activos" },
      { number: "100K+", label: "Rutas completadas" },
      { number: "95%", label: "Usuarios satisfechos" }
  ];

  // FAQs
  const faqs = [
      {
          question: "¿Cómo funciona Kahir.AI?",
          answer: "Kahir.AI utiliza inteligencia artificial avanzada para analizar tus preferencias y recomendar las mejores rutas basadas en tu experiencia, condición física y objetivos."
      },
      {
          question: "¿Es gratuito el servicio?",
          answer: "Ofrecemos una versión básica gratuita que incluye acceso a rutas y recomendaciones básicas. Nuestro plan premium añade características avanzadas."
      },
      {
          question: "¿Funciona sin conexión?",
          answer: "Sí, puedes descargar rutas y mapas para uso offline con nuestro plan premium. Las funcionalidades básicas funcionan sin conexión."
      },
      {
          question: "¿Qué nivel de experiencia necesito?",
          answer: "Kahir.AI está diseñado para todos los niveles, desde principiantes hasta expertos. El sistema adapta sus recomendaciones según tu nivel."
      }
  ];

  // Características del plan premium
  const premiumFeatures = [
      {
          title: "Rutas Premium",
          icon: "route",
          description: "Acceso a rutas exclusivas y descarga offline"
      },
      {
          title: "Pronóstico Avanzado",
          icon: "cloud-sun",
          description: "Previsión meteorológica detallada a 7 días"
      },
      {
          title: "Asistente Personal",
          icon: "robot",
          description: "Recomendaciones personalizadas ilimitadas"
      },
      {
          title: "Comunidad VIP",
          icon: "users",
          description: "Acceso a eventos y grupos exclusivos"
      }
  ];

  // Effect para el scroll
  React.useEffect(() => {
      const handleScroll = () => {
          setScrollY(window.scrollY);
          const newSection = Math.floor((window.scrollY / window.innerHeight));
          setActiveSection(newSection);
      };

      window.addEventListener('scroll', handleScroll);
      return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  // Sección Hero
  const createHeroSection = () => {
      return React.createElement('section', {
          key: 'hero-section',
          className: 'hero-section relative h-screen flex items-center justify-center'
      }, [
          React.createElement('div', {
              key: 'hero-background',
              className: 'absolute inset-0'
          }, [
              React.createElement('div', {
                  key: 'hero-overlay',
                  className: 'absolute inset-0 bg-gradient-to-r from-black/70 to-black/50'
              }),
              React.createElement('img', {
                  key: 'hero-image',
                  src: 'paginasespecializadas/skitouring/bannerski.jpg',
                  alt: 'Mountain landscape',
                  className: 'w-full h-full object-cover'
              })
          ]),
          React.createElement('div', {
              key: 'hero-content',
              className: 'relative z-10 text-center max-w-5xl mx-auto px-4'
          }, [
              React.createElement('h1', {
                  key: 'hero-title',
                  className: 'text-6xl md:text-8xl font-bold mb-8 text-white bg-clip-text'
              }, 'Kahir.AI'),
              React.createElement('p', {
                  key: 'hero-subtitle',
                  className: 'text-xl md:text-3xl mb-12 text-gray-200'
              }, 'Tu asistente personal para aventuras en los Pirineos'),
              React.createElement('div', {
                  key: 'hero-buttons',
                  className: 'flex flex-col md:flex-row gap-6 justify-center'
              }, [
                  React.createElement('a', {
                      key: 'cta-primary',
                      href: 'paginasespecializadas/inicio_sesion/gestion.php',
                      className: 'bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 px-8 rounded-xl text-xl transition duration-300 transform hover:-translate-y-1 hover:shadow-xl'
                  }, 'Comenzar Ahora'),
                  React.createElement('a', {
                      key: 'cta-secondary',
                      href: '#features',
                      className: 'bg-white/10 hover:bg-white/20 text-white font-bold py-4 px-8 rounded-xl text-xl transition duration-300 backdrop-blur-sm transform hover:-translate-y-1 hover:shadow-xl'
                  }, 'Explorar Funciones')
              ])
          ])
      ]);
  };

  // Sección de Características Principales
  const createFeaturesSection = () => {
      return React.createElement('section', {
          id: 'features',
          className: 'py-20 bg-gray-50',
          key: 'features-section'
      }, 
          React.createElement('div', {
              className: 'max-w-7xl mx-auto px-4'
          }, [
              React.createElement('h2', {
                  className: 'text-4xl md:text-5xl font-bold text-center mb-16'
              }, 'Características Principales'),
              React.createElement('div', {
                  className: 'grid md:grid-cols-2 lg:grid-cols-4 gap-8'
              }, mainSections.map((section, index) =>
                  React.createElement('div', {
                      key: `feature-${index}`,
                      className: 'bg-white rounded-xl shadow-lg p-6 transform hover:-translate-y-2 transition duration-300'
                  }, [
                      React.createElement('div', {
                          className: 'text-blue-600 text-4xl mb-4'
                      }, React.createElement('i', {
                          className: `fas fa-${section.icon}`
                      })),
                      React.createElement('h3', {
                          className: 'text-2xl font-bold mb-4'
                      }, section.title),
                      React.createElement('p', {
                          className: 'text-gray-600 mb-6'
                      }, section.subtitle),
                      React.createElement('ul', {
                          className: 'space-y-3'
                      }, section.features.map((feature, i) =>
                          React.createElement('li', {
                              key: `feature-item-${i}`,
                              className: 'flex items-start'
                          }, [
                              React.createElement('i', {
                                  className: 'fas fa-check text-green-500 mt-1 mr-3'
                              }),
                              React.createElement('span', {
                                  className: 'text-gray-700'
                              }, feature)
                          ])
                      ))
                  ])
              ))
          ])
      );
  };

  // Sección de Testimonios
  const createTestimonialsSection = () => {
      return React.createElement('section', {
          className: 'py-20 bg-white',
          key: 'testimonials-section'
      }, 
          React.createElement('div', {
              className: 'max-w-7xl mx-auto px-4'
          }, [
              React.createElement('h2', {
                  className: 'text-4xl md:text-5xl font-bold text-center mb-16'
              }, 'Lo que dicen nuestros usuarios'),
              React.createElement('div', {
                  className: 'grid md:grid-cols-3 gap-8'
              }, testimonials.map((testimonial, index) =>
                  React.createElement('div', {
                      key: `testimonial-${index}`,
                      className: 'bg-gray-50 rounded-xl p-6 shadow-lg hover:shadow-xl transition duration-300'
                  }, [
                      React.createElement('div', {
                          className: 'flex items-center mb-6'
                      }, [
                          React.createElement('img', {
                              src: testimonial.image,
                              alt: testimonial.name,
                              className: 'w-16 h-16 rounded-full mr-4'
                          }),
                          React.createElement('div', null, [
                              React.createElement('h3', {
                                  className: 'font-bold text-xl'
                              }, testimonial.name),
                              React.createElement('p', {
                                  className: 'text-gray-600'
                              }, testimonial.role)
                          ])
                      ]),
                      React.createElement('p', {
                          className: 'text-gray-700 italic'
                      }, `"${testimonial.text}"`)
                  ])
              ))
          ])
      );
  };

  // Sección de Estadísticas
  const createStatsSection = () => {
      return React.createElement('section', {
          className: 'py-20 bg-blue-600',
          key: 'stats-section'
      },
          React.createElement('div', {
              className: 'max-w-7xl mx-auto px-4'
          }, [
              React.createElement('div', {
                  className: 'grid grid-cols-2 md:grid-cols-4 gap-8'
              }, stats.map((stat, index) =>
                  React.createElement('div', {
                      key: `stat-${index}`,
                      className: 'text-center text-white'
                  }, [
                      React.createElement('div', {
                          className: 'text-5xl md:text-6xl font-bold mb-2'
                      }, stat.number),
                      React.createElement('div', {
                          className: 'text-xl opacity-90'
                      }, stat.label)
                  ])
              ))
          ])
      );
  };

  // Sección de Preguntas Frecuentes
  const createFaqsSection = () => {
      return React.createElement('section', {
          className: 'py-20 bg-gray-50',
          key: 'faqs-section'
      },
          React.createElement('div', {
              className: 'max-w-4xl mx-auto px-4'
          }, [
              React.createElement('h2', {
                  className: 'text-4xl md:text-5xl font-bold text-center mb-16'
              }, 'Preguntas Frecuentes'),
              React.createElement('div', {
                  className: 'space-y-6'
              }, faqs.map((faq, index) =>
                  React.createElement('div', {
                      key: `faq-${index}`,
                      className: 'bg-white rounded-xl shadow-md'
                  }, [
                      React.createElement('button', {
                          className: 'w-full px-6 py-4 text-left flex justify-between items-center',
                          onClick: () => setExpandedFaq(expandedFaq === index ? null : index)
                      }, [
                          React.createElement('span', {
                              className: 'text-xl font-semibold'
                          }, faq.question),
                          React.createElement('i', {
                              className: `fas fa-chevron-${expandedFaq === index ? 'up' : 'down'} 
                                       transition-transform duration-300`
                          })
                      ]),
                      React.createElement('div', {
                          className: `overflow-hidden transition-all duration-300
                          ${expandedFaq === index ? 'max-h-96 px-6 pb-4' : 'max-h-0'}`
                        },
                            React.createElement('p', {
                                className: 'text-gray-600'
                            }, faq.answer)
                        )
                    ])
                ))
            ])
        );
    };

    // Sección Premium
    const createPremiumSection = () => {
        return React.createElement('section', {
            className: 'py-20 bg-gradient-to-br from-blue-900 to-blue-600',
            key: 'premium-section'
        },
            React.createElement('div', {
                className: 'max-w-7xl mx-auto px-4'
            }, [
                React.createElement('h2', {
                    className: 'text-4xl md:text-5xl font-bold text-center text-white mb-16'
                }, 'Características Premium'),
                React.createElement('div', {
                    className: 'grid md:grid-cols-4 gap-8'
                }, premiumFeatures.map((feature, index) =>
                    React.createElement('div', {
                        key: `premium-${index}`,
                        className: 'bg-white/10 backdrop-blur-sm rounded-xl p-6 text-white hover:transform hover:-translate-y-2 transition duration-300'
                    }, [
                        React.createElement('div', {
                            className: 'text-4xl mb-4'
                        }, React.createElement('i', {
                            className: `fas fa-${feature.icon}`
                        })),
                        React.createElement('h3', {
                            className: 'text-xl font-bold mb-2'
                        }, feature.title),
                        React.createElement('p', {
                            className: 'opacity-90'
                        }, feature.description)
                    ])
                ))
            ])
        );
    };

    // Sección CTA Final
    const createFinalCTASection = () => {
        return React.createElement('section', {
            className: 'relative py-32',
            key: 'final-cta-section'
        }, [
            React.createElement('div', {
                className: 'absolute inset-0 z-0'
            }, [
                React.createElement('img', {
                    src: 'paginasespecializadas/skitouring/bannerski.jpg',
                    className: 'w-full h-full object-cover',
                    alt: 'Mountain background'
                }),
                React.createElement('div', {
                    className: 'absolute inset-0 bg-gradient-to-r from-blue-900/95 to-blue-600/95'
                })
            ]),
            React.createElement('div', {
                className: 'relative z-10 max-w-5xl mx-auto text-center px-4'
            }, [
                React.createElement('h2', {
                    className: 'text-4xl md:text-6xl font-bold mb-8 text-white'
                }, '¿Listo para comenzar tu aventura?'),
                React.createElement('p', {
                    className: 'text-xl md:text-2xl text-gray-200 mb-12'
                }, 'Únete a la comunidad de aventureros de los Pirineos'),
                React.createElement('div', {
                    className: 'flex flex-col md:flex-row justify-center gap-6'
                }, [
                    React.createElement('a', {
                        href: 'paginasespecializadas/inicio_sesion/gestion.php',
                        className: 'bg-white text-blue-600 hover:bg-blue-50 font-bold py-4 px-8 rounded-xl text-xl transition duration-300 transform hover:-translate-y-1 hover:shadow-xl'
                    }, 'Empezar Ahora'),
                    React.createElement('a', {
                        href: '#contact',
                        className: 'bg-transparent border-2 border-white text-white hover:bg-white/10 font-bold py-4 px-8 rounded-xl text-xl transition duration-300 transform hover:-translate-y-1 hover:shadow-xl'
                    }, 'Contactar')
                ])
            ])
        ]);
    };

    // Renderizar la página completa
    return React.createElement('div', {
        key: 'landing-page',
        className: 'bg-white'
    }, [
        createHeroSection(),
        createFeaturesSection(),
        createStatsSection(),
        createTestimonialsSection(),
        createPremiumSection(),
        createFaqsSection(),
        createFinalCTASection()
    ]);
};

// Exportar para uso global
window.LandingPage = LandingPage;

// Inicialización cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    const root = ReactDOM.createRoot(document.getElementById('landing-root'));
    root.render(React.createElement(LandingPage));

    // Detector de intersección para animaciones al hacer scroll
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
            }
        });
    }, {
        threshold: 0.1
    });

    // Observar todas las secciones
    document.querySelectorAll('section').forEach(section => {
        observer.observe(section);
    });
});